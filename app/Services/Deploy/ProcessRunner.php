<?php

namespace App\Services\Deploy;

use App\Exceptions\DeployException;
use Closure;
use Symfony\Component\Process\Process;

class ProcessRunner
{
    public function run(
        array $command,
        string $cwd,
        ?Closure $onOutput = null,
        ?int $timeout = null,
    ): ProcessResult {
        $timeout ??= config('deployer.default_timeout', 600);

        $process = new Process($command, $cwd, $this->environment());
        $process->setTimeout($timeout);

        $output = '';
        $errorOutput = '';

        $process->run(function (string $type, string $buffer) use (&$output, &$errorOutput, $onOutput): void {
            if ($type === Process::ERR) {
                $errorOutput .= $buffer;
            } else {
                $output .= $buffer;
            }

            if ($onOutput) {
                $onOutput($buffer);
            }
        });

        return new ProcessResult(
            successful: $process->isSuccessful(),
            output: $output,
            errorOutput: $errorOutput,
            exitCode: $process->getExitCode() ?? 1,
        );
    }

    public function runShell(
        string $command,
        string $cwd,
        ?Closure $onOutput = null,
        ?int $timeout = null,
    ): ProcessResult {
        $timeout ??= config('deployer.default_timeout', 600);

        $process = Process::fromShellCommandline($command, $cwd, $this->environment());
        $process->setTimeout($timeout);

        $output = '';
        $errorOutput = '';

        $process->run(function (string $type, string $buffer) use (&$output, &$errorOutput, $onOutput): void {
            if ($type === Process::ERR) {
                $errorOutput .= $buffer;
            } else {
                $output .= $buffer;
            }

            if ($onOutput) {
                $onOutput($buffer);
            }
        });

        return new ProcessResult(
            successful: $process->isSuccessful(),
            output: $output,
            errorOutput: $errorOutput,
            exitCode: $process->getExitCode() ?? 1,
        );
    }

    public function runOrFail(
        array $command,
        string $cwd,
        ?Closure $onOutput = null,
        ?int $timeout = null,
    ): ProcessResult {
        $result = $this->run($command, $cwd, $onOutput, $timeout);

        if (! $result->successful) {
            throw new DeployException($result->combinedOutput() ?: 'Command failed.');
        }

        return $result;
    }

    public function runShellOrFail(
        string $command,
        string $cwd,
        ?Closure $onOutput = null,
        ?int $timeout = null,
    ): ProcessResult {
        $result = $this->runShell($command, $cwd, $onOutput, $timeout);

        if (! $result->successful) {
            throw new DeployException($result->combinedOutput() ?: 'Command failed.');
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    private function environment(): array
    {
        $env = config('deployer.git_env', []);

        if ($profile = config('deployer.git_userprofile')) {
            $env['USERPROFILE'] = $profile;
            $env['HOME'] = $profile;
        }

        return $env;
    }
}
