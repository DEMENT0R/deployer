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
        $result = $this->runner->run(
            ['git', 'status', '--porcelain'],
            $cwd,
            null,
            30,
        );

        if (! $result->successful) {
            throw new GitException($result->combinedOutput() ?: 'Failed to read git status.');
        }

        return trim($result->output) !== '';
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
}
