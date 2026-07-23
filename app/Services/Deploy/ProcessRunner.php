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
     * Symfony merges our env with the inherited one, but the inherited set is
     * intersected with $_SERVER — under a web SAPI that holds request variables,
     * not the OS environment. On Windows the lost SystemRoot makes git fail with
     * "getaddrinfo() thread failed to start", so carry the essentials over here.
     *
     * @var list<string>
     */
    private const INHERITED_ENV_KEYS = [
        'SystemRoot',
        'SystemDrive',
        'windir',
        'ComSpec',
        'PATH',
        'PATHEXT',
        'TEMP',
        'TMP',
        'APPDATA',
        'LOCALAPPDATA',
        'ProgramData',
        'ProgramFiles',
        'ProgramFiles(x86)',
        'HOMEDRIVE',
        'HOMEPATH',
        'USERNAME',
    ];

    /**
     * @return array<string, string>
     */
    private function environment(): array
    {
        $env = [];

        foreach (self::INHERITED_ENV_KEYS as $key) {
            $value = getenv($key);

            if ($value !== false) {
                $env[$key] = $value;
            }
        }

        $env = array_merge($env, config('deployer.git_env', []));

        if ($profile = config('deployer.git_userprofile')) {
            $env['USERPROFILE'] = $profile;
            $env['HOME'] = $profile;
        }

        return $env;
    }
}
