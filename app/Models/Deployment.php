<?php

namespace App\Models;

use App\Enums\DeployAction;
use App\Enums\DeployStatus;
use App\Enums\DeployStep;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deployment extends Model
{
    protected $fillable = [
        'instance_id',
        'user_id',
        'branch',
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

    public function appendOutput(string $chunk): void
    {
        $this->output = ($this->output ?? '').$chunk;
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
