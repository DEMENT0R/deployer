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
        ?Closure $onOutput = null,
    ): void {
        $command = ['git', 'clone'];

        if ($branch !== null && $branch !== '') {
            $this->validateBranch($branch);
            $command[] = '--branch';
            $command[] = $branch;
        }

        $command[] = $repositoryUrl;
        $command[] = $target;

        $this->runner->runOrFail($command, dirname($target), $onOutput);
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
     * @return list<string>
     */
    public function remoteBranches(string $cwd, string $remote, ?Closure $onOutput = null): array
    {
        $result = $this->runner->run(
            ['git', 'for-each-ref', '--format=%(refname:short)', "refs/remotes/{$remote}/"],
            $cwd,
            $onOutput,
            30,
        );

        if (! $result->successful) {
            throw new GitException($result->combinedOutput() ?: 'Failed to list branches.');
        }

        $prefix = "{$remote}/";

        return collect(explode("\n", trim($result->output)))
            ->map(fn (string $ref) => trim($ref))
            ->map(fn (string $ref) => str_starts_with($ref, $prefix) ? substr($ref, strlen($prefix)) : $ref)
            ->filter()
            // origin/HEAD is a symbolic ref to the default branch, not a branch to check out.
            ->reject(fn (string $branch) => $branch === 'HEAD')
            ->unique()
            ->sort()
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
