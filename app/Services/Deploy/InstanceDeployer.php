<?php

namespace App\Services\Deploy;

use App\Enums\DeployAction;
use App\Enums\DeployStatus;
use App\Enums\DeployStep;
use App\Exceptions\DeployException;
use App\Models\Deployment;
use App\Models\Instance;
use Closure;
use Throwable;

class InstanceDeployer
{
    public function __construct(
        private readonly PathValidator $pathValidator,
        private readonly GitService $gitService,
        private readonly ProcessRunner $processRunner,
        private readonly GitBranchResolver $branchResolver,
    ) {}

    public function deploy(Deployment $deployment): void
    {
        $instance = $deployment->instance;
        $action = $deployment->action;
        $cwd = $this->pathValidator->resolve($instance);

        $steps = [];
        foreach ($action->steps() as $step) {
            $steps[$step->value] = 'pending';
        }
        $deployment->initializeSteps($steps);

        $onOutput = fn (string $chunk) => $deployment->appendOutput($chunk);

        try {
            foreach ($action->steps() as $step) {
                if ($step === DeployStep::Composer && blank($instance->composer_command)) {
                    $deployment->markStepSuccess($step);
                    $steps[$step->value] = 'skipped';
                    $deployment->update(['steps' => array_merge($deployment->steps ?? [], [$step->value => 'skipped'])]);

                    continue;
                }

                $this->step($deployment, $step, function () use ($step, $instance, $deployment, $cwd, $onOutput): void {
                    match ($step) {
                        DeployStep::Git => $this->runGit($instance, $deployment, $cwd, $onOutput),
                        DeployStep::Composer => $this->runComposer($instance, $cwd, $onOutput),
                        DeployStep::Migrate => $this->runMigrate($instance, $cwd, $onOutput),
                        DeployStep::Frontend => $this->runFrontend($instance, $cwd, $onOutput),
                    };
                });
            }

            $deployment->flushOutput();
            $deployment->update([
                'status' => DeployStatus::Success,
                'current_step' => null,
                'finished_at' => now(),
            ]);

            if ($action === DeployAction::Full || $action === DeployAction::Branch) {
                $this->branchResolver->forget($instance);
            }
        } catch (Throwable $e) {
            $deployment->appendOutput("\n[ERROR] ".$e->getMessage());
            $deployment->flushOutput();
            $deployment->update([
                'status' => DeployStatus::Failed,
                'exit_code' => $deployment->exit_code ?? 1,
                'finished_at' => now(),
            ]);

            throw $e;
        }
    }

    private function step(Deployment $deployment, DeployStep $step, callable $callback): void
    {
        $deployment->markStepRunning($step);
        $deployment->appendOutput("\n--- {$step->value} ---\n");

        try {
            $callback();
            $deployment->flushOutput();
            $deployment->markStepSuccess($step);
        } catch (Throwable $e) {
            $deployment->flushOutput();
            $steps = $deployment->steps ?? [];
            $steps[$step->value] = 'failed';
            $deployment->update([
                'steps' => $steps,
                'exit_code' => 1,
            ]);

            throw $e;
        }
    }

    private function runGit(Instance $instance, Deployment $deployment, string $cwd, Closure $onOutput): void
    {
        $branch = $deployment->branch ?? $instance->default_branch;

        if (! $branch) {
            throw new DeployException('Branch is required for git step.');
        }

        $this->gitService->deployBranch($cwd, $instance->git_remote, $branch, $onOutput);
    }

    private function runComposer(Instance $instance, string $cwd, Closure $onOutput): void
    {
        $command = $instance->composer_command;

        if (blank($command)) {
            return;
        }

        $this->processRunner->runShellOrFail($command, $cwd, $onOutput);
    }

    private function runMigrate(Instance $instance, string $cwd, Closure $onOutput): void
    {
        $this->processRunner->runShellOrFail($instance->migrate_command, $cwd, $onOutput);
    }

    private function runFrontend(Instance $instance, string $cwd, Closure $onOutput): void
    {
        $this->processRunner->runShellOrFail($instance->frontend_command, $cwd, $onOutput);
    }
}
