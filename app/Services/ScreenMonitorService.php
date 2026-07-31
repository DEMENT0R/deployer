<?php

namespace App\Services;

use App\Services\Deploy\ProcessRunner;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Список живых screen-сессий на хосте панели.
 *
 * Читаем не сокеты screen'а, а таблицу процессов: каталог сокетов (`/run/screen/S-<user>`)
 * имеет режим 0700 и виден только своему владельцу, поэтому `screen -ls` из-под пользователя
 * панели показал бы пустоту. `ps` же берёт данные из /proc и видит процессы всех пользователей.
 *
 * Плата за это — нет колонки Attached/Detached: она живёт в сокете, а не в процессе.
 */
class ScreenMonitorService
{
    /**
     * Мастер-процесс сессии screen перезапускает себя с argv[0] = "SCREEN" (капсом),
     * клиент `screen -r` остаётся строчным. Имя исполняемого файла у обоих одно,
     * поэтому выбираем по нему, а мастера от клиентов отделяем уже по argv.
     *
     * user:32 — иначе ps обрежет логин до восьми символов.
     *
     * @var list<string>
     */
    private const PS_COMMAND = ['ps', '-o', 'pid=,user:32=,etimes=,args=', '-C', 'screen,SCREEN'];

    private const TIMEOUT = 5;

    public function __construct(private readonly ProcessRunner $runner) {}

    /**
     * @param  string|null  $onlyUser  Показать сессии одного пользователя, в том числе скрытого по умолчанию.
     * @return array{
     *     available: bool,
     *     message: ?string,
     *     sessions: list<array{pid: int, user: string, name: ?string, command: string, uptime_seconds: int, started_at: string}>,
     *     users: list<array{name: string, count: int, hidden_by_default: bool}>,
     *     hidden_users: list<string>,
     *     hidden_count: int,
     *     filter: ?string,
     *     checked_at: string
     * }
     */
    public function snapshot(?string $onlyUser = null): array
    {
        if (! $this->supported()) {
            return $this->unavailable('GNU screen and ps are not available on a Windows host.', $onlyUser);
        }

        try {
            $result = $this->runner->run(self::PS_COMMAND, base_path(), null, self::TIMEOUT);
        } catch (Throwable $exception) {
            report($exception);

            return $this->unavailable(trim($exception->getMessage()) ?: 'Failed to run ps.', $onlyUser);
        }

        // Без единого совпадения ps выходит с кодом 1 и молчит — это «сессий нет».
        // Отсутствующий или упавший ps, в отличие от этого, пишет в stderr.
        if (! $result->successful && trim($result->errorOutput) !== '') {
            return $this->unavailable(trim($result->errorOutput), $onlyUser);
        }

        return $this->build($this->parse($result->output), $onlyUser);
    }

    /** Вынесено методом, чтобы разбор вывода `ps` тестировался и на Windows-машине разработчика. */
    protected function supported(): bool
    {
        return PHP_OS_FAMILY !== 'Windows';
    }

    /**
     * @return list<array{pid: int, user: string, name: ?string, command: string, uptime_seconds: int, started_at: string}>
     */
    private function parse(string $output): array
    {
        $sessions = [];

        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            if (! preg_match('/^\s*(\d+)\s+(\S+)\s+(\d+)\s+(.+?)\s*$/', $line, $matches)) {
                continue;
            }

            $command = $matches[4];

            if (! str_starts_with($command, 'SCREEN')) {
                continue;
            }

            $uptime = (int) $matches[3];

            $sessions[] = [
                'pid' => (int) $matches[1],
                'user' => $matches[2],
                'name' => $this->sessionName($command),
                'command' => $command,
                'uptime_seconds' => $uptime,
                'started_at' => Carbon::now()->subSeconds($uptime)->toIso8601String(),
            ];
        }

        usort($sessions, fn (array $a, array $b) => [$a['user'], $a['pid']] <=> [$b['user'], $b['pid']]);

        return $sessions;
    }

    /**
     * Имя сессии — аргумент ключа -S, который часто приезжает склеенным (`-dmS name`).
     * Сессии без -S именуются самим screen'ом как pid.tty.host, но этого в argv нет.
     */
    private function sessionName(string $command): ?string
    {
        $tokens = preg_split('/\s+/', $command) ?: [];

        foreach ($tokens as $index => $token) {
            if (preg_match('/^-[A-Za-z]*S$/', $token)) {
                return $tokens[$index + 1] ?? null;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $sessions
     * @return array<string, mixed>
     */
    private function build(array $sessions, ?string $onlyUser): array
    {
        $hiddenUsers = $this->hiddenUsers();

        $users = [];

        foreach ($sessions as $session) {
            $user = $session['user'];
            $users[$user] = ($users[$user] ?? 0) + 1;
        }

        ksort($users);

        // Явно выбранный пользователь показывается, даже если он в списке скрытых:
        // фильтр — это запрос «покажи именно их», а не предложение.
        $visible = $onlyUser === null
            ? array_values(array_filter($sessions, fn (array $s) => ! in_array($s['user'], $hiddenUsers, true)))
            : array_values(array_filter($sessions, fn (array $s) => $s['user'] === $onlyUser));

        return [
            'available' => true,
            'message' => null,
            'sessions' => $visible,
            'users' => array_map(
                fn (string $name, int $count) => [
                    'name' => $name,
                    'count' => $count,
                    'hidden_by_default' => in_array($name, $hiddenUsers, true),
                ],
                array_keys($users),
                array_values($users),
            ),
            'hidden_users' => $hiddenUsers,
            'hidden_count' => count($sessions) - count($visible),
            'filter' => $onlyUser,
            'checked_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailable(string $message, ?string $onlyUser): array
    {
        return [
            'available' => false,
            'message' => $message,
            'sessions' => [],
            'users' => [],
            'hidden_users' => $this->hiddenUsers(),
            'hidden_count' => 0,
            'filter' => $onlyUser,
            'checked_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * @return list<string>
     */
    private function hiddenUsers(): array
    {
        return array_values(config('deployer.screen_hidden_users', []));
    }
}
