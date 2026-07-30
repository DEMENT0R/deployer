<?php

namespace Tests\Unit;

use App\Exceptions\DeployException;
use App\Services\Deploy\GitService;
use App\Services\Deploy\ProcessResult;
use App\Services\Deploy\ProcessRunner;
use Closure;
use Tests\TestCase;

class GitCloneCommandTest extends TestCase
{
    public function test_clone_sets_a_non_default_remote_name(): void
    {
        $runner = $this->recordingRunner($captured);

        (new GitService($runner))->cloneRepository('git@github.com:org/repo.git', '/var/www/app', 'main', 'upstream');

        $this->assertContains('--origin', $captured);
        $this->assertContains('upstream', $captured);
        $this->assertContains('--branch', $captured);
        $this->assertSame('/var/www/app', end($captured));
    }

    public function test_clone_omits_origin_flag_for_default_remote(): void
    {
        $runner = $this->recordingRunner($captured);

        (new GitService($runner))->cloneRepository('git@github.com:org/repo.git', '/var/www/app', 'main', 'origin');

        $this->assertNotContains('--origin', $captured);
    }

    public function test_reset_hard_passes_the_commit(): void
    {
        $runner = $this->recordingRunner($captured);

        (new GitService($runner))->resetHard('/var/www/app', 'abc1234');

        $this->assertSame(['git', 'reset', '--hard', 'abc1234'], $captured);
    }

    public function test_reset_hard_rejects_a_non_hash(): void
    {
        $this->expectException(DeployException::class);

        (new GitService(new ProcessRunner))->resetHard('/var/www/app', 'main; rm -rf /');
    }

    /**
     * ProcessRunner, который не запускает git, а лишь запоминает переданную команду.
     */
    private function recordingRunner(?array &$captured): ProcessRunner
    {
        return new class($captured) extends ProcessRunner
        {
            public function __construct(private ?array &$captured) {}

            public function runOrFail(array $command, string $cwd, ?Closure $onOutput = null, ?int $timeout = null): ProcessResult
            {
                $this->captured = $command;

                return new ProcessResult(successful: true, output: '', errorOutput: '', exitCode: 0);
            }
        };
    }
}
