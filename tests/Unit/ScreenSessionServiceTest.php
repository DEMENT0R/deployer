<?php

namespace Tests\Unit;

use App\Exceptions\ScreenException;
use App\Models\Instance;
use App\Services\Deploy\PathValidator;
use App\Services\Deploy\ProcessResult;
use App\Services\Deploy\ProcessRunner;
use App\Services\ScreenMonitorService;
use App\Services\ScreenSessionService;
use Tests\TestCase;

class ScreenSessionServiceTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir().'/deployer-screen-test';

        if (! is_dir($this->path)) {
            mkdir($this->path, 0777, true);
        }

        config([
            'deployer.allowed_path_prefixes' => [sys_get_temp_dir()],
            'deployer.serve_command' => 'php artisan serve --host=0.0.0.0 --port={port}',
            // Проверка «сессия жива» в тестах не должна ничего ждать.
            'deployer.screen_start_check_delay' => 0,
        ]);
    }

    public function test_start_runs_serve_inside_a_detached_screen_in_the_instance_path(): void
    {
        $runner = $this->runner();
        $service = $this->service($runner, runningAfterStart: true);

        $session = $service->start($this->makeInstance());

        $this->assertSame('instance-1', $session);
        $this->assertSame([
            'screen', '-dmS', 'instance-1',
            'bash', '-lc', 'php artisan serve --host=0.0.0.0 --port=8080',
        ], $runner->commands[0]);
        $this->assertSame(realpath($this->path), $runner->cwds[0]);
    }

    public function test_start_refuses_an_instance_without_a_session_name_or_port(): void
    {
        $service = $this->service($this->runner(), runningAfterStart: true);

        $this->expectException(ScreenException::class);
        $this->expectExceptionMessage('has no screen session name or serve port configured');

        $service->start($this->makeInstance(session: null, port: null));
    }

    public function test_start_refuses_a_session_that_is_already_running(): void
    {
        $service = $this->service($this->runner(), runningBeforeStart: true, runningAfterStart: true);

        $this->expectException(ScreenException::class);
        $this->expectExceptionMessage('is already running');

        $service->start($this->makeInstance());
    }

    public function test_start_fails_when_the_session_dies_right_after_launch(): void
    {
        // screen -dmS всегда выходит с нулём, поэтому упавший внутри serve виден
        // только по тому, что сессии нет.
        $service = $this->service($this->runner(), runningAfterStart: false);

        $this->expectException(ScreenException::class);
        $this->expectExceptionMessage('died right after start');

        $service->start($this->makeInstance());
    }

    public function test_start_reports_a_missing_instance_path(): void
    {
        $service = $this->service($this->runner(), runningAfterStart: true);

        $this->expectException(ScreenException::class);

        $service->start($this->makeInstance(path: sys_get_temp_dir().'/deployer-screen-missing'));
    }

    public function test_stop_quits_the_session_by_name(): void
    {
        $runner = $this->runner();

        $this->service($runner, runningAfterStart: false)->stop('alpha-test');

        $this->assertSame(['screen', '-S', 'alpha-test', '-X', 'quit'], $runner->commands[0]);
    }

    public function test_stop_surfaces_the_error_screen_printed(): void
    {
        $runner = $this->runner(successful: false, errorOutput: 'No screen session found.');

        $this->expectException(ScreenException::class);
        $this->expectExceptionMessage('No screen session found.');

        $this->service($runner, runningAfterStart: false)->stop('someone-elses');
    }

    private function makeInstance(
        ?string $session = 'instance-1',
        ?int $port = 8080,
        ?string $path = null,
    ): Instance {
        return new Instance([
            'name' => 'MB 1',
            'path' => $path ?? $this->path,
            'screen_session' => $session,
            'serve_port' => $port,
        ]);
    }

    private function runner(bool $successful = true, string $errorOutput = ''): ProcessRunner
    {
        return new class($successful, $errorOutput) extends ProcessRunner
        {
            /** @var list<list<string>> */
            public array $commands = [];

            /** @var list<string> */
            public array $cwds = [];

            public function __construct(
                private readonly bool $successful,
                private readonly string $errorOutput,
            ) {}

            public function run(array $command, string $cwd, ?\Closure $onOutput = null, ?int $timeout = null): ProcessResult
            {
                $this->commands[] = $command;
                $this->cwds[] = $cwd;

                return new ProcessResult(
                    successful: $this->successful,
                    output: '',
                    errorOutput: $this->errorOutput,
                    exitCode: $this->successful ? 0 : 1,
                );
            }
        };
    }

    private function service(
        ProcessRunner $runner,
        bool $runningBeforeStart = false,
        bool $runningAfterStart = false,
    ): ScreenSessionService {
        $monitor = new class($runner, $runningBeforeStart, $runningAfterStart) extends ScreenMonitorService
        {
            private int $calls = 0;

            public function __construct(
                ProcessRunner $runner,
                private readonly bool $before,
                private readonly bool $after,
            ) {
                parent::__construct($runner);
            }

            public function exists(string $name): bool
            {
                return $this->calls++ === 0 ? $this->before : $this->after;
            }
        };

        return new class($runner, new PathValidator, $monitor) extends ScreenSessionService
        {
            protected function supported(): bool
            {
                return true;
            }
        };
    }
}
