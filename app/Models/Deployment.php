<?php

namespace App\Models;

use App\Enums\DeployAction;
use App\Enums\DeployStatus;
use App\Enums\DeployStep;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deployment extends Model
{
    protected $fillable = [
        'instance_id',
        'user_id',
        'branch',
        'commit_before',
        'commit_after',
        'action',
        'status',
        'current_step',
        'steps',
        'output',
        'exit_code',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'action' => DeployAction::class,
            'status' => DeployStatus::class,
            'current_step' => DeployStep::class,
            'steps' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRunning(): bool
    {
        return $this->status === DeployStatus::Running;
    }

    /**
     * Незавершённый деплой, который ещё подаёт признаки жизни. Такой держит
     * инстанс занятым; брошенные (см. isStale) не держат — иначе убитый воркер
     * запирал бы инстанс до ручной правки в БД.
     *
     * @param  Builder<Deployment>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', [DeployStatus::Pending, DeployStatus::Running])
            ->where('updated_at', '>=', now()->subSeconds(self::staleAfter()));
    }

    public function isStale(): bool
    {
        if (! in_array($this->status, [DeployStatus::Pending, DeployStatus::Running], true)) {
            return false;
        }

        return $this->updated_at !== null
            && $this->updated_at->lt(now()->subSeconds(self::staleAfter()));
    }

    /** Сколько секунд деплой ещё живой, если о нём ничего не слышно. */
    public static function staleAfter(): int
    {
        return max(1, (int) config('deployer.stale_after', 960));
    }

    /** Сколько деплой ждёт воркера; null — если он уже не в очереди. */
    public function queuedSeconds(): ?int
    {
        if ($this->status !== DeployStatus::Pending || $this->created_at === null) {
            return null;
        }

        return (int) $this->created_at->diffInSeconds(now());
    }

    /**
     * Output arrives chunk by chunk; writing every one of them rewrites the whole
     * longText column, so buffer and flush on size or time. Callers must flush at
     * step boundaries so the log is complete when a step ends or fails.
     */
    private const OUTPUT_FLUSH_BYTES = 4096;

    private const OUTPUT_FLUSH_SECONDS = 1.0;

    private string $outputBuffer = '';

    private ?float $lastOutputFlush = null;

    public function appendOutput(string $chunk): void
    {
        $this->outputBuffer .= $chunk;
        $this->lastOutputFlush ??= microtime(true);

        if (strlen($this->outputBuffer) >= self::OUTPUT_FLUSH_BYTES
            || microtime(true) - $this->lastOutputFlush >= self::OUTPUT_FLUSH_SECONDS) {
            $this->flushOutput();
        }
    }

    public function flushOutput(): void
    {
        $this->lastOutputFlush = microtime(true);

        if ($this->outputBuffer === '') {
            return;
        }

        $this->output = ($this->output ?? '').$this->outputBuffer;
        $this->outputBuffer = '';
        $this->save();
    }

    /**
     * @param  array<string, string>  $steps
     */
    public function initializeSteps(array $steps): void
    {
        $this->steps = $steps;
        $this->save();
    }

    public function markStepRunning(DeployStep $step): void
    {
        $steps = $this->steps ?? [];
        $steps[$step->value] = 'running';
        $this->update([
            'current_step' => $step,
            'steps' => $steps,
        ]);
    }

    public function markStepSuccess(DeployStep $step): void
    {
        $steps = $this->steps ?? [];
        $steps[$step->value] = 'success';
        $this->update([
            'steps' => $steps,
        ]);
    }
}
