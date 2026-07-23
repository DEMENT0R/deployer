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
        $this->runner->runOrFail(['git', 'checkout', $branch], $cwd, $onOutput);
        $this->runner->runOrFail(['git', 'pull', $remote, $branch], $cwd, $onOutput);
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
            ->map(fn (string $ref) => str_starts_with($ref, $prefix) ? substr($ref, strlen($prefix)) : $ref)
            ->filter()
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
