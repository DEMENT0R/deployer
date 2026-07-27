<?php

namespace Tests\Unit;

use App\Models\Instance;
use App\Services\Deploy\GitService;
use App\Services\Deploy\PathValidator;
use App\Services\Deploy\ProcessResult;
use App\Services\Deploy\ProcessRunner;
use App\Services\InstanceStatusService;
use Tests\TestCase;

class InstanceStatusServiceTest extends TestCase
{
    private ?string $base = null;

    protected function tearDown(): void
    {
        if ($this->base !== null && is_dir($this->base)) {
            @rmdir($this->base);
        }

        parent::tearDown();
    }

    public function test_collects_branch_commit_divergence_and_stashes(): void
    {
        $runner = $this->fakeRunner([
            'rev-parse' => "feature/x\n",
            'status' => " M app/Foo.php\n?? bar.txt\n",
            'log' => "abc1234567\x1fabc1234\x1fAdd the thing\x1fAda\x1f2026-07-27T10:00:00+00:00\n",
            'stash' => "stash@{0}: WIP on main\n",
            'rev-list' => "0\t2\n",
        ]);

        $status = $this->service($runner)->inspect($this->makeInstance());

        $this->assertSame('ok', $status['status']);
        $this->assertSame('feature/x', $status['branch']);
        $this->assertSame('origin/feature/x', $status['tracking']);
        $this->assertSame(0, $status['ahead']);
        $this->assertSame(2, $status['behind']);
        $this->assertTrue($status['dirty']);
        $this->assertSame(2, $status['changed_files']);
        $this->assertSame(1, $status['stashes']);
        $this->assertSame('abc1234', $status['commit']['short_sha']);
        $this->assertSame('Add the thing', $status['commit']['subject']);
    }

    public function test_detached_head_skips_the_remote_comparison(): void
    {
        $runner = $this->fakeRunner([
            'rev-parse' => "HEAD\n",
            'status' => '',
            'log' => "abc1234567\x1fabc1234\x1fAdd the thing\x1fAda\x1f2026-07-27T10:00:00+00:00\n",
            'stash' => '',
            'rev-list' => "0\t0\n",
        ]);

        $status = $this->service($runner)->inspect($this->makeInstance());

        $this->assertSame('ok', $status['status']);
        $this->assertNull($status['branch']);
        $this->assertNull($status['tracking']);
        $this->assertNull($status['ahead']);
        $this->assertFalse($status['dirty']);
        $this->assertNotContains(
            'rev-list',
            array_map(fn (array $command) => $command[1] ?? '', $runner->commands),
        );
    }

    public function test_failing_git_is_reported_instead_of_throwing(): void
    {
        $runner = $this->fakeRunner(['status' => 'fatal: not a git repository'], failing: ['status']);

        $status = $this->service($runner)->inspect($this->makeInstance());

        $this->assertSame('git_error', $status['status']);
        $this->assertStringContainsString('not a git repository', $status['message']);
        $this->assertNull($status['branch']);
    }

    public function test_path_outside_allowed_prefix_is_reported(): void
    {
        $instance = $this->makeInstance();
        config(['deployer.allowed_path_prefixes' => ['/var/www']]);
        $instance->allowed_path_prefix = null;

        $status = $this->service($this->fakeRunner([]))->inspect($instance);

        $this->assertSame('path_error', $status['status']);
        $this->assertNotNull($status['message']);
    }

    private function service(ProcessRunner $runner): InstanceStatusService
    {
        return new InstanceStatusService(new PathValidator, new GitService($runner));
    }

    private function makeInstance(): Instance
    {
        $this->base = sys_get_temp_dir().'/deployer-status-'.uniqid();
        mkdir($this->base, 0755, true);

        config(['deployer.allowed_path_prefixes' => [$this->base]]);

        return new Instance([
            'path' => $this->base,
            'git_remote' => 'origin',
            'allowed_path_prefix' => $this->base,
        ]);
    }

    /**
     * @param  array<string, string>  $outputs  ключ — git-подкоманда
     * @param  list<string>  $failing
     */
    private function fakeRunner(array $outputs, array $failing = []): ProcessRunner
    {
        return new class($outputs, $failing) extends ProcessRunner
        {
            /** @var list<list<string>> */
            public array $commands = [];

            /**
             * @param  array<string, string>  $outputs
             * @param  list<string>  $failing
             */
            public function __construct(private readonly array $outputs, private readonly array $failing) {}

            public function run(array $command, string $cwd, ?\Closure $onOutput = null, ?int $timeout = null): ProcessResult
            {
                $this->commands[] = $command;
                $subcommand = $command[1] ?? '';
                $successful = ! in_array($subcommand, $this->failing, true);

                return new ProcessResult(
                    successful: $successful,
                    output: $successful ? ($this->outputs[$subcommand] ?? '') : '',
                    errorOutput: $successful ? '' : ($this->outputs[$subcommand] ?? 'failed'),
                    exitCode: $successful ? 0 : 128,
                );
            }
        };
    }
}
