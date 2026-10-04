<?php

use App\Services\DeploymentPruner;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('deployer:prune', function (DeploymentPruner $pruner) {
    $result = $pruner->prune();

    if ($result === null) {
        $this->info('Pruning is disabled: DEPLOYER_OUTPUT_KEEP_DAYS is 0.');

        return;
    }

    $this->info("Cleared the output of {$result['outputs']} deployment(s), removed {$result['failed_jobs']} failed job(s).");
})->purpose('Remove old deployment output and failed queue jobs (DEPLOYER_OUTPUT_KEEP_DAYS)');
