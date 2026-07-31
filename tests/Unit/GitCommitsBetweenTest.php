<?php

namespace Tests\Unit;

use App\Exceptions\DeployException;
use App\Exceptions\GitException;
use App\Services\Deploy\GitService;
use App\Services\Deploy\ProcessResult;
use App\Services\Deploy\ProcessRunner;
use Closure;
use Tests\TestCase;

class GitCommitsBetweenTest extends TestCase
{
    public function test_parses_the_commit_range(): void
    {
        $output = implode("\x1e", [
            implode("\x1f", ['a'.str_repeat('1', 39), 'a111111', 'Add search', 'Ada', '2026-07-30T10:00:00+00:00']),
            implode("\x1f", ['b'.str_repeat('2', 39), 'b222222', 'Fix: don\'t drop the filter', 'Bob', '']),
            '',
        ]);

        $runner = $this->cannedRunner($output, true, $captured);

        $commits = (new GitService($runner))->commitsBetween('/var/www/app', 'abc1234', 'def5678', 50);

        $this->assertCount(2, $commits);
        $this->assertSame('a111111', $commits[0]['short_sha']);
        $this->assertSame('Add search', $commits[0]['subject']);
        $this->assertSame('Ada', $commits[0]['author']);
        $this->assertSame('2026-07-30T10:00:00+00:00', $commits[0]['committed_at']);

        // Пустая дата приходит как null, а тема с апострофом не должна ломать разбор.
        $this->assertNull($commits[1]['committed_at']);
        $this->assertSame("Fix: don't drop the filter", $commits[1]['subject']);

        $this->assertContains('abc1234..def5678', $captured);
        $this->assertContains('--max-count=50', $captured);
    }

    public function test_empty_range_yields_no_commits(): void
    {
        $runner = $this->cannedRunner('', true, $captured);

        $this->assertSame([], (new GitService($runner))->commitsBetween('/var/www/app', 'abc1234', 'def5678'));
    }

    public function test_git_failure_is_reported(): void
    {
        $runner = $this->cannedRunner('bad revision', false, $captured);

        $this->expectException(GitException::class);
        (new GitService($runner))->commitsBetween('/var/www/app', 'abc1234', 'def5678');
    }

    public function test_rejects_a_non_hash_range(): void
    {
        $this->expectException(DeployException::class);

        (new GitService(new ProcessRunner))->commitsBetween('/var/www/app', 'main', 'HEAD; rm -rf /');
    }

    /**
     * ProcessRunner с заранее заданным выводом: git не запускается, команда запоминается.
     */
    private function cannedRunner(string $output, bool $successful, ?array &$captured): ProcessRunner
    {
        return new class($output, $successful, $captured) extends ProcessRunner
        {
            public function __construct(
                private string $cannedOutput,
                private bool $ok,
                private ?array &$captured,
            ) {}

            public function run(array $command, string $cwd, ?Closure $onOutput = null, ?int $timeout = null): ProcessResult
            {
                $this->captured = $command;

                return new ProcessResult(
                    successful: $this->ok,
                    output: $this->cannedOutput,
                    errorOutput: $this->ok ? '' : $this->cannedOutput,
                    exitCode: $this->ok ? 0 : 128,
                );
            }
        };
    }
}
