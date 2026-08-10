<?php

namespace App\Services;

use App\Exceptions\ScreenException;
use App\Models\Instance;
use App\Services\Deploy\PathValidator;
use App\Services\Deploy\ProcessRunner;
use Throwable;

/**
 * Запуск и остановка screen-сессий со стендами (`artisan serve`) с Admin → Screens.
 *
 * Читающая половина вкладки живёт в ScreenMonitorService: она обходится таблицей процессов
 * и потому видит сессии всех пользователей. Здесь так не выйдет — `screen -X` ходит в сокет
 * (`/run/screen/S-<user>`, режим 0700), поэтому глушить можно только сессии того пользователя,
 * под которым работает панель. Чужую сессию screen объявит несуществующей; это и показываем.
 */
class ScreenSessionService
{
    private const TIMEOUT = 15;

    public function __construct(
        private readonly ProcessRunner $runner,
        private readonly PathValidator $pathValidator,
        private readonly ScreenMonitorService $monitor,
    ) {}

    /**
     * @return string Имя запущенной сессии.
     *
     * @throws ScreenException
     */
    public function start(Instance $instance): string
    {
        $this->ensureSupported();

        if (! $instance->isServable()) {
            throw new ScreenException(
                "Instance \"{$instance->name}\" has no screen session name or serve port configured."
            );
        }

        $session = $instance->screen_session;

        if ($this->monitor->exists($session)) {
            throw new ScreenException("Session \"{$session}\" is already running.");
        }

        try {
            $cwd = $this->pathValidator->resolve($instance);
        } catch (Throwable $exception) {
            throw new ScreenException($exception->getMessage(), previous: $exception);
        }

        $command = str_replace(
            '{port}',
            (string) $instance->serve_port,
            (string) config('deployer.serve_command'),
        );

        // `bash -lc`, а не разбор строки на аргументы: команда задаётся настройкой и остаётся
        // shell-строкой, как composer/frontend-команды инстанса, а логин-профиль подтягивает
        // PATH до нужного php (phpenv, кастомные сборки).
        $result = $this->runner->run(
            ['screen', '-dmS', $session, 'bash', '-lc', $command],
            $cwd,
            null,
            self::TIMEOUT,
        );

        if (! $result->successful) {
            throw new ScreenException($result->combinedOutput() ?: 'Failed to start the screen session.');
        }

        // screen отсоединяется мгновенно и всегда с нулём: упавшую внутри команду видно
        // только по тому, что сессии нет. Иначе панель отрапортует об успехе на пустом месте.
        usleep(max(0, (int) config('deployer.screen_start_check_delay', 700)) * 1000);

        if (! $this->monitor->exists($session)) {
            throw new ScreenException(
                "Session \"{$session}\" died right after start. Check the serve command and the instance path: {$command}"
            );
        }

        return $session;
    }

    /**
     * @throws ScreenException
     */
    public function stop(string $session): void
    {
        $this->ensureSupported();

        $result = $this->runner->run(
            ['screen', '-S', $session, '-X', 'quit'],
            base_path(),
            null,
            self::TIMEOUT,
        );

        if (! $result->successful) {
            throw new ScreenException($result->combinedOutput()
                ?: "Failed to stop session \"{$session}\". Sessions of other users can only be stopped by their owner.");
        }
    }

    /**
     * Перезапуск сессии стенда после правки `.env` из Admin → Instances: `artisan serve` держит
     * переменные окружения в памяти процесса и перечитает файл, только заново стартовав.
     * Выключенную сессию так не поднимаем — правка `.env` не повод включать погашенный стенд.
     *
     * @return bool Была ли сессия действительно перезапущена.
     *
     * @throws ScreenException
     */
    public function restart(Instance $instance): bool
    {
        if (! $instance->isServable() || ! $this->monitor->exists($instance->screen_session)) {
            return false;
        }

        $this->stop($instance->screen_session);
        $this->start($instance);

        return true;
    }

    /** Отдельным методом ради тестов: на Windows-машине разработчика screen'а нет. */
    protected function supported(): bool
    {
        return PHP_OS_FAMILY !== 'Windows';
    }

    /**
     * @throws ScreenException
     */
    private function ensureSupported(): void
    {
        if (! $this->supported()) {
            throw new ScreenException('GNU screen is not available on a Windows host.');
        }
    }
}
