<?php

namespace App\Http\Controllers;

use App\Enums\DeployAction;
use App\Enums\DeployStatus;
use App\Http\Requests\StoreDeploymentRequest;
use App\Jobs\DeployInstanceJob;
use App\Models\Deployment;
use App\Models\Instance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            // active(), а не просто статус: брошенная воркером строка иначе запирает
            // инстанс навсегда. От реальной гонки страхует кэш-лок в джобе.
            $hasRunning = $instance->deployments()->active()->exists();

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

    /**
     * Откат к состоянию до последнего успешного деплоя: сбрасываем рабочее дерево к его
     * commit_before. Целевой SHA кладём в branch деплоя отката — оттуда его берёт InstanceDeployer.
     */
    public function rollback(Request $request, Instance $instance): RedirectResponse
    {
        if (! $request->user()->can('deploy', $instance)) {
            abort(403);
        }

        $target = $instance->deployments()
            ->where('status', DeployStatus::Success)
            ->whereIn('action', [DeployAction::Full, DeployAction::Branch])
            ->whereNotNull('commit_before')
            ->latest('id')
            ->first();

        if (! $target) {
            return back()->withErrors(['deploy' => 'No previous deployment to roll back to.']);
        }

        $lock = Cache::lock("deploy:create:{$instance->id}", 10);

        if (! $lock->get()) {
            return back()->withErrors(['deploy' => 'A deployment is already in progress for this instance.']);
        }

        try {
            if ($instance->deployments()->active()->exists()) {
                return back()->withErrors(['deploy' => 'A deployment is already in progress for this instance.']);
            }

            $deployment = Deployment::create([
                'instance_id' => $instance->id,
                'user_id' => $request->user()->id,
                'branch' => $target->commit_before,
                'action' => DeployAction::Rollback,
                'status' => DeployStatus::Pending,
            ]);
        } finally {
            $lock->release();
        }

        DeployInstanceJob::dispatch($deployment->id);

        return back()->with('success', 'Rollback queued.');
    }
}
