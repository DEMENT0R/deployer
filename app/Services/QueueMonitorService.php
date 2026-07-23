<?php

namespace App\Services;

use App\Enums\DeployStatus;
use App\Models\Deployment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QueueMonitorService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $now = now()->timestamp;

        $pendingJobs = DB::table('jobs')
            ->whereNull('reserved_at')
            ->where('available_at', '<=', $now)
            ->count();

        $reservedJobs = DB::table('jobs')
            ->whereNotNull('reserved_at')
            ->count();

        $delayedJobs = DB::table('jobs')
            ->whereNull('reserved_at')
            ->where('available_at', '>', $now)
            ->count();

        return [
            'connection' => config('queue.default'),
            'summary' => [
                'pending' => $pendingJobs,
                'running' => $reservedJobs,
                'delayed' => $delayedJobs,
                'failed' => DB::table('failed_jobs')->count(),
                'total_in_queue' => DB::table('jobs')->count(),
            ],
            'deployments' => [
                'pending' => Deployment::query()->where('status', DeployStatus::Pending)->count(),
                'running' => Deployment::query()->where('status', DeployStatus::Running)->count(),
                'failed_recent' => Deployment::query()
                    ->where('status', DeployStatus::Failed)
                    ->where('finished_at', '>=', now()->subDay())
                    ->count(),
            ],
            'jobs' => $this->mapJobs(
                DB::table('jobs')->orderByDesc('id')->limit(50)->get()
            ),
            'failed_jobs' => $this->mapFailedJobs(
                DB::table('failed_jobs')->orderByDesc('failed_at')->limit(50)->get()
            ),
            'active_deployments' => $this->mapDeployments(
                Deployment::query()
                    ->with(['instance:id,name', 'user:id,name'])
                    ->whereIn('status', [DeployStatus::Pending, DeployStatus::Running])
                    ->latest()
                    ->get()
            ),
            'recent_deployments' => $this->mapDeployments(
                Deployment::query()
                    ->with(['instance:id,name', 'user:id,name'])
                    ->latest()
                    ->limit(20)
                    ->get()
            ),
        ];
    }

    /**
     * @param  Collection<int, object>  $jobs
     * @return list<array<string, mixed>>
     */
    private function mapJobs(Collection $jobs): array
    {
        $deploymentIds = $jobs
            ->map(fn (object $job) => $this->parsePayload($job->payload)['deployment_id'])
            ->filter()
            ->unique()
            ->values();

        $deploymentInstances = Deployment::query()
            ->whereIn('id', $deploymentIds)
            ->pluck('instance_id', 'id');

        return $jobs->map(function (object $job) use ($deploymentInstances): array {
            $payload = $this->parsePayload($job->payload);
            $deploymentId = $payload['deployment_id'];

            return [
                'id' => $job->id,
                'queue' => $job->queue,
                'name' => $payload['display_name'],
                'deployment_id' => $deploymentId,
                'instance_id' => $deploymentId ? $deploymentInstances->get($deploymentId) : null,
                'attempts' => $job->attempts,
                'status' => $job->reserved_at ? 'running' : 'pending',
                'created_at' => Carbon::createFromTimestamp($job->created_at)->toIso8601String(),
                'reserved_at' => $job->reserved_at
                    ? Carbon::createFromTimestamp($job->reserved_at)->toIso8601String()
                    : null,
            ];
        })->all();
    }

    /**
     * @param  Collection<int, object>  $jobs
     * @return list<array<string, mixed>>
     */
    private function mapFailedJobs(Collection $jobs): array
    {
        return $jobs->map(function (object $job): array {
            $payload = $this->parsePayload($job->payload);
            $exception = (string) $job->exception;
            $firstLine = strtok($exception, "\n") ?: $exception;

            return [
                'id' => $job->id,
                'uuid' => $job->uuid,
                'queue' => $job->queue,
                'name' => $payload['display_name'],
                'deployment_id' => $payload['deployment_id'],
                'failed_at' => Carbon::parse($job->failed_at)->toIso8601String(),
                'exception' => mb_substr($firstLine, 0, 200),
            ];
        })->all();
    }

    /**
     * @param  Collection<int, Deployment>  $deployments
     * @return list<array<string, mixed>>
     */
    private function mapDeployments(Collection $deployments): array
    {
        return $deployments->map(fn (Deployment $deployment): array => [
            'id' => $deployment->id,
            'instance' => $deployment->instance?->name,
            'instance_id' => $deployment->instance_id,
            'user' => $deployment->user?->name,
            'branch' => $deployment->branch,
            'action' => $deployment->action->value,
            'status' => $deployment->status->value,
            'current_step' => $deployment->current_step?->value,
            'started_at' => $deployment->started_at?->toIso8601String(),
            'finished_at' => $deployment->finished_at?->toIso8601String(),
        ])->all();
    }

    /**
     * @return array{display_name: string, deployment_id: ?int}
     */
    private function parsePayload(string $payload): array
    {
        $data = json_decode($payload, true);
        $displayName = is_array($data) ? ($data['displayName'] ?? class_basename($data['job'] ?? 'Unknown')) : 'Unknown';

        $deploymentId = null;
        $command = is_array($data) ? ($data['data']['command'] ?? '') : '';

        // Matches DeployInstanceJob::$deploymentId — our own property, not Eloquent internals.
        if (is_string($command) && preg_match('/s:12:"deploymentId";i:(\d+)/', $command, $matches)) {
            $deploymentId = (int) $matches[1];
        }

        return [
            'display_name' => class_basename($displayName),
            'deployment_id' => $deploymentId,
        ];
    }
}
