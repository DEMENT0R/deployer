<?php

namespace App\Jobs;

use App\Enums\DeployStatus;
use App\Models\Deployment;
use App\Notifications\DeploymentFinished;
use App\Services\Deploy\InstanceDeployer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class DeployInstanceJob implements ShouldQueue
{
    use Queueable;

    public int $timeout;

    public function __construct(
        public int $deploymentId,
    ) {
        $this->timeout = config('deployer.job_timeout', 900);
    }

    public function handle(InstanceDeployer $deployer): void
    {
        $deployment = Deployment::with('instance')->find($this->deploymentId);

        if (! $deployment || ! $deployment->instance) {
            return;
        }

        $instance = $deployment->instance;

        $lock = Cache::lock("deploy:instance:{$instance->id}", $this->timeout);

        if (! $lock->get()) {
            $deployment->update([
                'status' => DeployStatus::Failed,
                'output' => ($deployment->output ?? '')."\nAnother deployment is already running for this instance.",
                'finished_at' => now(),
            ]);

            return;
        }

        try {
            $deployment->update([
                'status' => DeployStatus::Running,
                'started_at' => now(),
            ]);

            $deployer->deploy($deployment);
        } catch (Throwable $e) {
            report($e);
        } finally {
            $lock->release();
        }

        $this->notifyInitiator($deployment);
    }

    /**
     * Уведомление о завершении деплоя. Сбой отправки не должен ронять джобу — деплой уже прошёл.
     */
    private function notifyInitiator(Deployment $deployment): void
    {
        if (DeploymentFinished::enabledChannels() === []) {
            return;
        }

        $deployment->refresh()->loadMissing('user', 'instance');

        if (! $deployment->user || ! $deployment->instance) {
            return;
        }

        try {
            $deployment->user->notify(new DeploymentFinished($deployment));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
