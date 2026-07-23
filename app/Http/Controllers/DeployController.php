<?php

namespace App\Http\Controllers;

use App\Enums\DeployStatus;
use App\Http\Requests\StoreDeploymentRequest;
use App\Jobs\DeployInstanceJob;
use App\Models\Deployment;
use App\Models\Instance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;

class DeployController extends Controller
{
    public function store(StoreDeploymentRequest $request, Instance $instance): RedirectResponse
    {
        $validated = $request->validated();

        // Check and insert must be atomic, otherwise two clicks both pass the check
        // and the loser only finds out once its job hits the deploy lock.
        $lock = Cache::lock("deploy:create:{$instance->id}", 10);

        if (! $lock->get()) {
            return back()->withErrors(['deploy' => 'A deployment is already in progress for this instance.']);
        }

        try {
            $hasRunning = $instance->deployments()
                ->whereIn('status', [DeployStatus::Pending, DeployStatus::Running])
                ->exists();

            if ($hasRunning) {
                return back()->withErrors(['deploy' => 'A deployment is already in progress for this instance.']);
            }

            $deployment = Deployment::create([
                'instance_id' => $instance->id,
                'user_id' => $request->user()->id,
                'branch' => $validated['branch'] ?? null,
                'action' => $validated['action'],
                'status' => DeployStatus::Pending,
            ]);
        } finally {
            $lock->release();
        }

        DeployInstanceJob::dispatch($deployment->id);

        return back()->with('success', 'Deployment queued.');
    }
}
