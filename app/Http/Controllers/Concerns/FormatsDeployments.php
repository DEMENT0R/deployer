<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Deployment;

trait FormatsDeployments
{
    /**
     * Форма деплоя для страницы инстанса: шаги, лог и признаки того,
     * что деплой завис в очереди или брошен воркером.
     *
     * @return array<string, mixed>
     */
    protected function formatDeployment(Deployment $deployment): array
    {
        $queuedSeconds = $deployment->queuedSeconds();

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
            'is_stale' => $deployment->isStale(),
            'queued_seconds' => $queuedSeconds,
            // Слишком долго ждёт воркера — скорее всего, queue:work просто не запущен.
            'queue_stuck' => $queuedSeconds !== null
                && $queuedSeconds >= max(1, (int) config('deployer.queue_warn_after', 15)),
        ];
    }

    /**
     * То же плюс поля, которые нужны только в отдельном просмотре деплоя.
     * Тянет связь user — на списках не использовать.
     *
     * @return array<string, mixed>
     */
    protected function formatDeploymentDetail(Deployment $deployment): array
    {
        return array_merge($this->formatDeployment($deployment), [
            'user' => $deployment->user?->name,
            'exit_code' => $deployment->exit_code,
            'duration_seconds' => $this->deploymentDuration($deployment),
        ]);
    }

    protected function deploymentDuration(Deployment $deployment): ?int
    {
        if (! $deployment->started_at || ! $deployment->finished_at) {
            return null;
        }

        return (int) $deployment->started_at->diffInSeconds($deployment->finished_at);
    }
}
