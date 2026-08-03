<?php

namespace App\Http\Controllers;

use App\Enums\DeployStatus;
use App\Http\Controllers\Concerns\FormatsDeployments;
use App\Models\Deployment;
use App\Models\Instance;
use App\Services\DeploymentCommitsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DeploymentController extends Controller
{
    use FormatsDeployments;

    /** Лог и шаги одного деплоя — история на странице инстанса открывает его модалкой. */
    public function show(Instance $instance, Deployment $deployment): JsonResponse
    {
        $this->authorize('view', $instance);

        return response()->json(
            $this->formatDeploymentDetail($deployment->loadMissing('user:id,name'))
        );
    }

    /**
     * Коммиты, уехавшие в этот деплой. Отдельным запросом, а не вместе с логом:
     * это git-подпроцесс, он не должен задерживать открытие модалки и попадать в поллинг.
     */
    public function commits(
        Instance $instance,
        Deployment $deployment,
        DeploymentCommitsService $commitsService,
    ): JsonResponse {
        $this->authorize('view', $instance);

        return response()->json($commitsService->forDeployment($deployment->setRelation('instance', $instance)));
    }

    /**
     * Снимает зависший деплой с инстанса. Процесс в воркере при этом не убивается:
     * если он ещё жив, он доработает и сам перепишет статус, а параллельный запуск
     * всё равно не пройдёт — его не пустит кэш-лок в DeployInstanceJob.
     */
    public function cancel(Request $request, Instance $instance, Deployment $deployment): RedirectResponse
    {
        // Админу отмена нужна и на выключенном инстансе: deploy-политика требует is_active,
        // и строку зависшего деплоя на отключённом стенде иначе нечем закрыть — а видна она
        // на Admin → Queues, где чистить такие строки и приходится.
        if (! $request->user()->isAdmin()) {
            $this->authorize('deploy', $instance);
        }

        if (! in_array($deployment->status, [DeployStatus::Pending, DeployStatus::Running], true)) {
            return back()->withErrors(['deploy' => 'This deployment has already finished.']);
        }

        $deployment->update([
            'status' => DeployStatus::Failed,
            'exit_code' => $deployment->exit_code ?? 1,
            'finished_at' => now(),
            'output' => ($deployment->output ?? '')
                ."\n[CANCELLED] Marked as failed by {$request->user()->name}.",
        ]);

        return back()->with('success', 'Deployment marked as failed.');
    }
}
