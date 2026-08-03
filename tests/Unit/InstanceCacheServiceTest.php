<?php

namespace Tests\Unit;

use App\Exceptions\CacheClearException;
use App\Exceptions\PathValidationException;
use App\Models\Instance;
use App\Services\Deploy\PathValidator;
use App\Services\Deploy\ProcessResult;
use App\Services\Deploy\ProcessRunner;
use App\Services\InstanceCacheService;
use Closure;
use Tests\TestCase;

class InstanceCacheServiceTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir().'/deployer-cache-test';

        if (! is_dir($this->path)) {
            mkdir($this->path, 0777, true);
        }

        config(['deployer.allowed_path_prefixes' => [sys_get_temp_dir()]]);
    }

    public function test_the_instance_command_runs_in_the_instance_directory(): void
    {
        $runner = $this->runner();

        $output = (new InstanceCacheService($runner, new PathValidator))
            ->clear($this->makeInstance());

        $this->assertSame('php artisan config:clear', $runner->commands[0]);
        $this->assertSame(realpath($this->path), $runner->cwds[0]);
        $this->assertSame('Configuration cache cleared successfully.', $output);
    }

    public function test_an_instance_without_a_command_is_refused(): void
    {
        $runner = $this->runner();

        $this->expectException(CacheClearException::class);
        $this->expectExceptionMessage('No cache command is set');

        (new InstanceCacheService($runner, new PathValidator))
            ->clear($this->makeInstance(command: null));
    }

    public function test_a_failed_command_surfaces_its_output(): void
    {
        $runner = $this->runner(successful: false, output: 'Could not open input file: artisan');

        $this->expectException(CacheClearException::class);
        $this->expectExceptionMessage('Could not open input file: artisan');

        (new InstanceCacheService($runner, new PathValidator))->clear($this->makeInstance());
    }

    public function test_a_missing_instance_path_is_reported(): void
    {
        $this->expectException(PathValidationException::class);

        (new InstanceCacheService($this->runner(), new PathValidator))
            ->clear($this->makeInstance(path: sys_get_temp_dir().'/deployer-cache-missing'));
    }

    private function makeInstance(
        ?string $command = 'php artisan config:clear',
        ?string $path = null,
    ): Instance {
        return new Instance([
            'name' => 'MB 1',
            'path' => $path ?? $this->path,
            'cache_command' => $command,
        ]);
    }

    private function runner(
        bool $successful = true,
        string $output = 'Configuration cache cleared successfully.',
    ): ProcessRunner {
        return new class($successful, $output) extends ProcessRunner
        {
            /** @var list<string> */
            public array $commands = [];

            /** @var list<string> */
            public array $cwds = [];

            public function __construct(
                private readonly bool $successful,
                private readonly string $output,
            ) {}

            public function runShell(string $command, string $cwd, ?Closure $onOutput = null, ?int $timeout = null): ProcessResult
            {
                $this->commands[] = $command;
                $this->cwds[] = $cwd;

                return new ProcessResult(
                    successful: $this->successful,
                    output: $this->output,
                    errorOutput: '',
                    exitCode: $this->successful ? 0 : 1,
                );
            }
        };
    }
}
