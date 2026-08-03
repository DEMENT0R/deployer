<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FormatsDeployments;
use App\Models\Deployment;
use App\Models\Instance;
use App\Services\Deploy\GitBranchResolver;
use App\Services\InstanceEnvService;
use App\Services\InstanceStatusService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InstanceController extends Controller
{
    use FormatsDeployments;

    /** Сколько последних деплоев показывать в истории на странице инстанса. */
    private const HISTORY_LIMIT = 20;

    public function index(Request $request, InstanceEnvService $envService): Response
    {
        $this->authorize('viewAny', Instance::class);

        $instances = $request->user()
            ->accessibleInstances()
            ->with(['deployments' => fn ($query) => $query->latest()->limit(1)])
            ->orderBy('name')
            ->get();

        return Inertia::render('Instances/Index', [
            'instances' => $instances->map(fn (Instance $instance) => $this->formatInstanceSummary($instance)),
            // Имя БД лежит в .env целевого проекта: поход в файловую систему по каждому инстансу,
            // на недоступном пути ещё и небыстрый. Список карточек ждать этого не должен.
            'databases' => Inertia::defer(fn () => $instances->mapWithKeys(
                fn (Instance $instance) => [$instance->id => $envService->databaseName($instance)]
            )),
        ]);
    }

    public function show(
        Request $request,
        Instance $instance,
        GitBranchResolver $branchResolver,
        InstanceStatusService $statusService,
    ): Response {
        $this->authorize('view', $instance);

        $branches = ['branches' => [], 'current' => null];
        $branchError = null;

        try {
            $branches = $branchResolver->resolve($instance);
        } catch (\Throwable $e) {
            // The page still renders — the picker is empty and says why.
            report($e);
            $branchError = trim($e->getMessage()) ?: 'Failed to load branches.';
        }

        $activeDeployment = $instance->deployments()->active()->latest()->first();

        $latestDeployment = $instance->deployments()->latest()->first();

        return Inertia::render('Instances/Show', [
            'instance' => $this->formatInstance($instance),
            'branches' => $branches['branches'],
            'currentBranch' => $branches['current'],
            'branchError' => $branchError,
            'deployment' => $activeDeployment
                ? $this->formatDeployment($activeDeployment)
                : ($latestDeployment ? $this->formatDeployment($latestDeployment) : null),
            // Замыкание: поллинг деплоя ходит с `only`, история в него не входит и не перезапрашивается.
            'deployments' => fn () => $this->deploymentHistory($instance),
            // Дюжина git-команд не должна задерживать первую отрисовку — Inertia дотянет отдельным запросом.
            'gitStatus' => Inertia::defer(fn () => $statusService->inspect($instance)),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function deploymentHistory(Instance $instance): array
    {
        return $instance->deployments()
            ->with('user:id,name')
            // Деплои одной минуты различает только id — по created_at они идут вровень.
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->map(fn (Deployment $deployment) => [
                'id' => $deployment->id,
                'branch' => $deployment->branch,
                'action' => $deployment->action->value,
                'status' => $deployment->status->value,
                'user' => $deployment->user?->name,
                'exit_code' => $deployment->exit_code,
                'started_at' => $deployment->started_at?->toIso8601String(),
                'finished_at' => $deployment->finished_at?->toIso8601String(),
                'duration_seconds' => $this->deploymentDuration($deployment),
            ])
            ->all();
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
            'url' => $instance->url,
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
            'url' => $instance->url,
            'default_branch' => $instance->default_branch,
            'git_remote' => $instance->git_remote,
            // Саму команду наружу не отдаём — странице хватает знания, есть ли что запускать.
            'has_composer_command' => filled($instance->composer_command),
        ];
    }
}
