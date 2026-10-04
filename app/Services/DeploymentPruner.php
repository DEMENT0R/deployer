<?php

namespace App\Services;

use App\Enums\DeployStatus;
use App\Models\Deployment;
use Illuminate\Support\Facades\DB;

/**
 * Чистка того, что панель копит сама: полный вывод каждого деплоя (composer и npm — это
 * сотни килобайт на запуск) и упавшие джобы очереди.
 *
 * Записи деплоев не удаляем — история «кто, что, когда» нужна и через год, и на неё
 * ссылается аудит. Заменяем только вывод: он нужен, пока разбираются, почему упало.
 */
class DeploymentPruner
{
    /** Начало заглушки; по нему же узнаём уже вычищенный вывод и не переписываем его заново. */
    public const MARKER = '[Output removed';

    /**
     * @return array{outputs: int, failed_jobs: int}|null null — чистка выключена
     */
    public function prune(): ?array
    {
        $days = (int) config('deployer.output_keep_days');

        if ($days <= 0) {
            return null;
        }

        $cutoff = now()->subDays($days);

        $outputs = Deployment::query()
            ->whereNotIn('status', [DeployStatus::Pending, DeployStatus::Running])
            ->where('finished_at', '<', $cutoff)
            ->whereNotNull('output')
            ->where('output', 'not like', self::MARKER.'%')
            // toBase: updated_at не трогаем — по нему мониторы судят, когда деплой последний раз жил.
            ->toBase()
            ->update([
                'output' => self::MARKER." after {$days} days to save space: DEPLOYER_OUTPUT_KEEP_DAYS.]",
            ]);

        return ['outputs' => $outputs, 'failed_jobs' => $this->pruneFailedJobs($cutoff)];
    }

    /** Упавшие джобы — только когда они лежат в БД: файловый и прочие драйверы не наши. */
    private function pruneFailedJobs(\DateTimeInterface $cutoff): int
    {
        if (! in_array(config('queue.failed.driver'), ['database', 'database-uuids'], true)) {
            return 0;
        }

        return DB::connection(config('queue.failed.database'))
            ->table(config('queue.failed.table', 'failed_jobs'))
            ->where('failed_at', '<', $cutoff)
            ->delete();
    }
}
