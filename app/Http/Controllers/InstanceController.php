<?php

namespace App\Http\Controllers;

use App\Enums\DeployStatus;
use App\Models\Instance;
use App\Services\Deploy\GitBranchResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InstanceController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Instance::class);

        $instances = $request->user()
            ->accessibleInstances()
            ->with(['deployments' => fn ($query) => $query->latest()->limit(1)])
            ->orderBy('name')
            ->get()
            ->map(fn (Instance $instance) => $this->formatInstanceSummary($instance));

        return Inertia::render('Instances/Index', [
            'instances' => $instances,
        ]);
    }

    public function show(Request $request, Instance $instance, GitBranchResolver $branchResolver): Response
    {
        $this->authorize('view', $instance);

        $branches = ['branches' => [], 'current' => null];

        try {
            $branches = $branchResolver->resolve($instance);
        } catch (\Throwable) {
            // Path may not exist in dev; UI still renders.
        }

        $activeDeployment = $instance->deployments()
            ->whereIn('status', [DeployStatus::Running, DeployStatus::Pending])
            ->latest()
            ->first();

        $latestDeployment = $instance->deployments()->latest()->first();

        return Inertia::render('Instances/Show', [
            'instance' => $this->formatInstance($instance),
            'branches' => $branches['branches'],
            'currentBranch' => $branches['current'],
            'deployment' => $activeDeployment
                ? $this->formatDeployment($activeDeployment)
                : ($latestDeployment ? $this->formatDeployment($latestDeployment) : null),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatInstanceSummary(Instance $instance): array
    {
        $latest = $instance->deployments->first();

        return [
            'id' => $instance->id,
            'name' => $instance->name,
            'path' => $instance->path,
            'default_branch' => $instance->default_branch,
            'latest_deployment' => $latest ? $this->formatDeployment($latest) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatInstance(Instance $instance): array
    {
        return [
            'id' => $instance->id,
            'name' => $instance->name,
            'path' => $instance->path,
            'default_branch' => $instance->default_branch,
            'git_remote' => $instance->git_remote,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatDeployment(\App\Models\Deployment $deployment): array
    {
        return [
            'id' => $deployment->id,
            'branch' => $deployment->branch,
            'action' => $deployment->action->value,
            'status' => $deployment->status->value,
            'current_step' => $deployment->current_step?->value,
            'steps' => $deployment->steps ?? [],
            'output' => $deployment->output,
            'started_at' => $deployment->started_at?->toIso8601String(),
            'finished_at' => $deployment->finished_at?->toIso8601String(),
        ];
    }
}
