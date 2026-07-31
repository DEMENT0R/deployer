<?php

namespace App\Services\Deploy;

use App\Exceptions\DeployException;
use App\Exceptions\GitException;
use Closure;

class GitService
{
    public function __construct(
        private readonly ProcessRunner $runner,
    ) {}

    public function validateBranch(string $branch): void
    {
        if (! preg_match(config('deployer.branch_pattern'), $branch)) {
            throw new DeployException('Invalid branch name.');
        }
    }

    /**
     * Первичный clone рабочей копии. Каталог назначения git создаёт сам, поэтому
     * процесс запускаем из родительской директории (её существование проверяет
     * PathValidator::resolveForClone).
     */
    public function cloneRepository(
        string $repositoryUrl,
        string $target,
        ?string $branch,
        string $remote = 'origin',
        ?Closure $onOutput = null,
    ): void {
        $command = ['git', 'clone'];

        // По умолчанию git заводит remote «origin». Инстанс может ждать другой (git_remote),
        // иначе последующие fetch/pull пойдут по несуществующему remote — задаём его сразу.
        if ($remote !== '' && $remote !== 'origin') {
            $command[] = '--origin';
            $command[] = $remote;
        }

        if ($branch !== null && $branch !== '') {
            $this->validateBranch($branch);
            $command[] = '--branch';
            $command[] = $branch;
        }

        $command[] = $repositoryUrl;
        $command[] = $target;

        $this->runner->runOrFail($command, dirname($target), $onOutput);
    }

    /**
     * Коммиты в диапазоне `$from..$to` — то, что уехало в деплой. Разделители: \x1f между
     * полями, \x1e между коммитами (в теме коммита и имени автора они не встречаются).
     *
     * @return list<array{sha: string, short_sha: string, subject: string, author: string, committed_at: ?string}>
     */
    public function commitsBetween(string $cwd, string $from, string $to, int $limit = 100): array
    {
        foreach ([$from, $to] as $commit) {
            if (! preg_match('/^[0-9a-f]{7,40}$/i', $commit)) {
                throw new DeployException('Invalid commit hash.');
            }
        }

        $result = $this->runner->run(
            ['git', 'log', '--max-count='.$limit, '--format=%H%x1f%h%x1f%s%x1f%an%x1f%cI%x1e', "{$from}..{$to}"],
            $cwd,
            null,
            30,
        );

        if (! $result->successful) {
            throw new GitException($result->combinedOutput() ?: 'Failed to read commit range.');
        }

        $commits = [];

        foreach (explode("\x1e", $result->output) as $record) {
            $record = trim($record);

            if ($record === '') {
                continue;
            }

            $parts = explode("\x1f", $record);

            if (count($parts) < 5) {
                continue;
            }

            $commits[] = [
                'sha' => $parts[0],
                'short_sha' => $parts[1],
                'subject' => $parts[2],
                'author' => $parts[3],
                'committed_at' => $parts[4] !== '' ? $parts[4] : null,
            ];
        }

        return $commits;
    }

    /**
     * Жёсткий откат рабочего дерева к указанному коммиту. Хэш валидируем отдельно:
     * в reset --hard он подставляется как аргумент, ветковый паттерн сюда не годится.
     */
    public function resetHard(string $cwd, string $commit, ?Closure $onOutput = null): void
    {
        if (! preg_match('/^[0-9a-f]{7,40}$/i', $commit)) {
            throw new DeployException('Invalid commit hash.');
        }

        $this->runner->runOrFail(['git', 'reset', '--hard', $commit], $cwd, $onOutput);
    }

    public function deployBranch(
        string $cwd,
        string $remote,
        string $branch,
        ?Closure $onOutput = null,
    ): void {
        $this->validateBranch($branch);

        $this->runner->runOrFail(['git', 'fetch', '--all'], $cwd, $onOutput);

        if (config('deployer.auto_stash', true)) {
            $this->stashLocalChanges($cwd, $onOutput);
        }

        $this->runner->runOrFail(['git', 'checkout', $branch], $cwd, $onOutput);
        $this->runner->runOrFail(['git', 'pull', $remote, $branch], $cwd, $onOutput);
    }

    /**
     * Локальные изменения в целевом проекте валят checkout/pull. Прячем их в stash,
     * а не сбрасываем: изменения остаются доступны через `git stash list` в проекте.
     * Untracked включаем (они тоже мешают checkout), а вот ignored — нет: там лежат
     * .env, vendor, node_modules целевого проекта.
     */
    private function stashLocalChanges(string $cwd, ?Closure $onOutput = null): void
    {
        if (! $this->isDirty($cwd)) {
            return;
        }

        if ($onOutput) {
            $onOutput("\n[deployer] Обнаружены локальные изменения, прячем в stash.\n");
        }

        $this->runner->runOrFail(
            ['git', 'stash', 'push', '--include-untracked', '--message', 'deployer auto-stash '.now()->toDateTimeString()],
            $cwd,
            $onOutput,
        );
    }

    private function isDirty(string $cwd): bool
    {
        return $this->statusLines($cwd) !== [];
    }

    /**
     * Порционный `git status` целевого проекта: по строке на изменённый файл.
     *
     * @return list<string>
     */
    public function statusLines(string $cwd): array
    {
        $result = $this->runner->run(
            ['git', 'status', '--porcelain'],
            $cwd,
            null,
            30,
        );

        if (! $result->successful) {
            throw new GitException($result->combinedOutput() ?: 'Failed to read git status.');
        }

        return $this->nonEmptyLines($result->output);
    }

    public function fetchAll(string $cwd, ?Closure $onOutput = null): void
    {
        $this->runner->runOrFail(['git', 'fetch', '--all'], $cwd, $onOutput);
    }

    /**
     * Ветки remote, свежие сверху. Сортирует git (`--sort=-committerdate`), а не PHP:
     * порядок должен совпадать с датой, которую отдаём наружу. Свежесть считается по
     * локальным remote-tracking ссылкам — она настолько свежа, насколько свеж последний fetch.
     *
     * @return list<array{name: string, committed_at: ?string}>
     */
    public function remoteBranches(string $cwd, string $remote, ?Closure $onOutput = null): array
    {
        $result = $this->runner->run(
            [
                'git', 'for-each-ref',
                '--sort=-committerdate',
                '--format=%(refname:short)%1f%(committerdate:iso-strict)',
                "refs/remotes/{$remote}/",
            ],
            $cwd,
            $onOutput,
            30,
        );

        if (! $result->successful) {
            throw new GitException($result->combinedOutput() ?: 'Failed to list branches.');
        }

        $prefix = "{$remote}/";

        return collect(preg_split('/\R/', trim($result->output)) ?: [])
            ->map(fn (string $line) => explode("\x1f", trim($line)))
            ->map(fn (array $parts) => [
                'name' => str_starts_with($parts[0], $prefix)
                    ? substr($parts[0], strlen($prefix))
                    : $parts[0],
                'committed_at' => ($parts[1] ?? '') !== '' ? $parts[1] : null,
            ])
            ->filter(fn (array $branch) => $branch['name'] !== '')
            // origin/HEAD is a symbolic ref to the default branch, not a branch to check out.
            ->reject(fn (array $branch) => $branch['name'] === 'HEAD')
            ->unique('name')
            ->values()
            ->all();
    }

    public function currentBranch(string $cwd): ?string
    {
        $result = $this->runner->run(
            ['git', 'rev-parse', '--abbrev-ref', 'HEAD'],
            $cwd,
            null,
            30,
        );

        if (! $result->successful) {
            return null;
        }

        $branch = trim($result->output);

        return $branch !== '' && $branch !== 'HEAD' ? $branch : null;
    }

    /**
     * Разделитель полей — \x1f: он не встречается ни в теме коммита, ни в имени автора.
     *
     * @return array{sha: string, short_sha: string, subject: string, author: string, committed_at: ?string}|null
     */
    public function headCommit(string $cwd): ?array
    {
        $result = $this->runner->run(
            ['git', 'log', '-1', '--format=%H%x1f%h%x1f%s%x1f%an%x1f%cI'],
            $cwd,
            null,
            30,
        );

        // Свежий репозиторий без коммитов — не ошибка, просто нечего показывать.
        if (! $result->successful) {
            return null;
        }

        $parts = explode("\x1f", trim($result->output));

        if (count($parts) < 5 || $parts[0] === '') {
            return null;
        }

        return [
            'sha' => $parts[0],
            'short_sha' => $parts[1],
            'subject' => $parts[2],
            'author' => $parts[3],
            'committed_at' => $parts[4] !== '' ? $parts[4] : null,
        ];
    }

    /**
     * Насколько рабочее дерево разошлось с remote-tracking веткой. Считается по локальным
     * ссылкам: цифры настолько свежи, насколько свеж последний fetch.
     *
     * @return array{ahead: int, behind: int}|null
     */
    public function divergence(string $cwd, string $remote, string $branch): ?array
    {
        $this->validateBranch($branch);

        $result = $this->runner->run(
            ['git', 'rev-list', '--left-right', '--count', "HEAD...{$remote}/{$branch}"],
            $cwd,
            null,
            30,
        );

        // Ветки нет на remote (или fetch ни разу не проходил) — сравнивать не с чем.
        if (! $result->successful || ! preg_match('/^(\d+)\s+(\d+)$/', trim($result->output), $matches)) {
            return null;
        }

        return [
            'ahead' => (int) $matches[1],
            'behind' => (int) $matches[2],
        ];
    }

    public function stashCount(string $cwd): ?int
    {
        $result = $this->runner->run(
            ['git', 'stash', 'list'],
            $cwd,
            null,
            30,
        );

        if (! $result->successful) {
            return null;
        }

        return count($this->nonEmptyLines($result->output));
    }

    /**
     * @return list<string>
     */
    private function nonEmptyLines(string $output): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\R/', trim($output)) ?: []),
            fn (string $line) => $line !== '',
        ));
    }
}
