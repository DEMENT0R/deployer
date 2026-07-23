<?php

namespace App\Jobs;

use App\Enums\DeployStatus;
use App\Models\Deployment;
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
        public Deployment $deployment,
    ) {
        $this->timeout = config('deployer.job_timeout', 900);
    }

    public function handle(InstanceDeployer $deployer): void
    {
        $deployment = $this->deployment->fresh(['instance']);
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
    }
}
