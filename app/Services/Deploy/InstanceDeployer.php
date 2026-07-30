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
        // Clone бутстрапит каталог, которого ещё нет, — путь резолвим по-другому.
        $cwd = $action === DeployAction::Clone
            ? $this->pathValidator->resolveForClone($instance)
            : $this->pathValidator->resolve($instance);

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
                        DeployStep::Clone => $this->runClone($instance, $cwd, $onOutput),
                        DeployStep::Rollback => $this->runRollback($deployment, $cwd, $onOutput),
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

            if (in_array($action, [DeployAction::Full, DeployAction::Branch, DeployAction::Rollback], true)) {
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

    private function runClone(Instance $instance, string $target, Closure $onOutput): void
    {
        if (blank($instance->repository_url)) {
            throw new DeployException('Repository URL is not set for this instance.');
        }

        $this->gitService->cloneRepository(
            $instance->repository_url,
            $target,
            $instance->default_branch,
            $instance->git_remote,
            $onOutput,
        );
    }

    private function runGit(Instance $instance, Deployment $deployment, string $cwd, Closure $onOutput): void
    {
        $branch = $deployment->branch ?? $instance->default_branch;

        if (! $branch) {
            throw new DeployException('Branch is required for git step.');
        }

        // Запоминаем HEAD до pull — это точка, к которой позволит вернуться будущий откат.
        $deployment->update(['commit_before' => $this->gitService->headCommit($cwd)['sha'] ?? null]);

        $this->gitService->deployBranch($cwd, $instance->git_remote, $branch, $onOutput);

        $deployment->update(['commit_after' => $this->gitService->headCommit($cwd)['sha'] ?? null]);
    }

    private function runRollback(Deployment $deployment, string $cwd, Closure $onOutput): void
    {
        // Целевой коммит контроллер кладёт в branch деплоя отката (см. DeployController::rollback).
        $target = $deployment->branch;

        if (blank($target)) {
            throw new DeployException('No rollback target commit.');
        }

        $deployment->update(['commit_before' => $this->gitService->headCommit($cwd)['sha'] ?? null]);

        $this->gitService->resetHard($cwd, $target, $onOutput);

        $deployment->update(['commit_after' => $this->gitService->headCommit($cwd)['sha'] ?? null]);
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
