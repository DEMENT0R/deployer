<?php

namespace App\Http\Controllers;

use App\Enums\DeployStatus;
use App\Http\Requests\StoreDeploymentRequest;
use App\Jobs\DeployInstanceJob;
use App\Models\Deployment;
use App\Models\Instance;
use Illuminate\Http\RedirectResponse;

class DeployController extends Controller
{
    public function store(StoreDeploymentRequest $request, Instance $instance): RedirectResponse
    {
        $hasRunning = $instance->deployments()
            ->whereIn('status', [DeployStatus::Pending, DeployStatus::Running])
            ->exists();

        if ($hasRunning) {
            return back()->withErrors(['deploy' => 'A deployment is already in progress for this instance.']);
        }

        $validated = $request->validated();

        $deployment = Deployment::create([
            'instance_id' => $instance->id,
            'user_id' => $request->user()->id,
            'branch' => $validated['branch'] ?? null,
            'action' => $validated['action'],
            'status' => DeployStatus::Pending,
        ]);

        DeployInstanceJob::dispatch($deployment);

        return back()->with('success', 'Deployment queued.');
    }
}
