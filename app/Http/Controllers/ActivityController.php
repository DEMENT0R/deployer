<?php

namespace App\Http\Controllers;

use App\Enums\DeployStatus;
use App\Http\Controllers\Concerns\FormatsDeployments;
use App\Models\Deployment;
use App\Models\Instance;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityController extends Controller
{
    use FormatsDeployments;

    /** Сколько деплоев на странице ленты. */
    private const PER_PAGE = 30;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Instance::class);

        $status = DeployStatus::tryFrom($request->string('status')->toString())?->value;

        // Лента ограничена инстансами, к которым есть доступ: тестер видит только свои,
        // админ — все активные.
        $instanceIds = $request->user()->accessibleInstances()->select('instances.id');

        $deployments = Deployment::query()
            ->whereIn('instance_id', $instanceIds)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->with(['user:id,name', 'instance:id,name'])
            // Деплои одной минуты различает только id — по created_at они идут вровень.
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('Activity/Index', [
            'deployments' => $deployments->through(fn (Deployment $deployment) => $this->formatRow($deployment)),
            'filters' => ['status' => $status],
            'statuses' => array_map(fn (DeployStatus $case) => $case->value, DeployStatus::cases()),
        ]);
    }

    /**
     * Строка ленты. Лог (`output`) сознательно не отдаём: на странице их тридцать,
     * а вывод деплоя ничем не ограничен — за полным логом идут на страницу деплоя.
     *
     * @return array<string, mixed>
     */
    private function formatRow(Deployment $deployment): array
    {
        return [
            'id' => $deployment->id,
            'branch' => $deployment->branch,
            'action' => $deployment->action->value,
            'status' => $deployment->status->value,
            'user' => $deployment->user?->name,
            'exit_code' => $deployment->exit_code,
            'is_stale' => $deployment->isStale(),
            'created_at' => $deployment->created_at?->toIso8601String(),
            'started_at' => $deployment->started_at?->toIso8601String(),
            'finished_at' => $deployment->finished_at?->toIso8601String(),
            'duration_seconds' => $this->deploymentDuration($deployment),
            'instance' => [
                'id' => $deployment->instance->id,
                'name' => $deployment->instance->name,
            ],
        ];
    }
}
