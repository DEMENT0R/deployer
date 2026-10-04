<?php

namespace Tests\Feature;

use App\Enums\DeployAction;
use App\Enums\DeployStatus;
use App\Jobs\DeployInstanceJob;
use App\Models\Deployment;
use App\Models\Instance;
use App\Models\User;
use App\Services\Deploy\InstanceDeployer;
use App\Services\DeploymentPruner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class DeploymentPrunerTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_output_is_replaced_and_the_record_stays(): void
    {
        config(['deployer.output_keep_days' => 30]);

        $old = $this->deployment(DeployStatus::Success, now()->subDays(40), 'composer install ...');
        $fresh = $this->deployment(DeployStatus::Failed, now()->subDays(5), 'npm ERR!');
        $oldUpdatedAt = $old->updated_at->toDateTimeString();

        $this->assertSame(['outputs' => 1, 'failed_jobs' => 0], app(DeploymentPruner::class)->prune());

        $old->refresh();
        $this->assertStringStartsWith(DeploymentPruner::MARKER, $old->output);
        $this->assertSame(DeployStatus::Success, $old->status);
        // По updated_at мониторы судят, жив ли деплой, — чистка его не трогает.
        $this->assertSame($oldUpdatedAt, $old->updated_at->toDateTimeString());
        $this->assertSame('npm ERR!', $fresh->refresh()->output);

        // Повторный прогон уже вычищенное не переписывает.
        $this->assertSame(0, app(DeploymentPruner::class)->prune()['outputs']);
    }

    /** Застрявший в running деплой — повод разбираться, его вывод не трогаем. */
    public function test_unfinished_deployments_keep_their_output(): void
    {
        config(['deployer.output_keep_days' => 30]);

        $stuck = $this->deployment(DeployStatus::Running, now()->subDays(40), 'still going');

        app(DeploymentPruner::class)->prune();

        $this->assertSame('still going', $stuck->refresh()->output);
    }

    public function test_old_failed_jobs_are_removed(): void
    {
        config(['deployer.output_keep_days' => 30]);

        foreach ([40, 5] as $days) {
            DB::table('failed_jobs')->insert([
                'uuid' => (string) Str::uuid(),
                'connection' => 'database',
                'queue' => 'default',
                'payload' => '{}',
                'exception' => 'boom',
                'failed_at' => now()->subDays($days),
            ]);
        }

        $this->assertSame(1, app(DeploymentPruner::class)->prune()['failed_jobs']);
        $this->assertSame(1, DB::table('failed_jobs')->count());
    }

    public function test_zero_keeps_everything(): void
    {
        config(['deployer.output_keep_days' => 0]);

        $old = $this->deployment(DeployStatus::Success, now()->subDays(400), 'log');

        $this->assertNull(app(DeploymentPruner::class)->prune());
        $this->assertSame('log', $old->refresh()->output);
    }

    public function test_a_finished_deploy_prunes_old_output(): void
    {
        config(['deployer.output_keep_days' => 30, 'deployer.notify_in_panel' => false]);

        $old = $this->deployment(DeployStatus::Success, now()->subDays(40), 'old log');
        $pending = $this->deployment(DeployStatus::Pending, null, null);

        $deployer = Mockery::mock(InstanceDeployer::class);
        $deployer->shouldReceive('deploy')->once()->andReturnUsing(
            fn (Deployment $d) => $d->update(['status' => DeployStatus::Success, 'finished_at' => now()]),
        );

        (new DeployInstanceJob($pending->id))->handle($deployer);

        $this->assertStringStartsWith(DeploymentPruner::MARKER, $old->refresh()->output);
    }

    public function test_the_command_reports_what_it_removed(): void
    {
        config(['deployer.output_keep_days' => 30]);
        $this->deployment(DeployStatus::Success, now()->subDays(40), 'old log');

        $this->artisan('deployer:prune')
            ->expectsOutputToContain('Cleared the output of 1 deployment(s)')
            ->assertSuccessful();
    }

    private function deployment(DeployStatus $status, ?\DateTimeInterface $finishedAt, ?string $output): Deployment
    {
        return Deployment::create([
            'instance_id' => Instance::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
            'branch' => 'main',
            'action' => DeployAction::Full,
            'status' => $status,
            'output' => $output,
            'finished_at' => $finishedAt,
        ]);
    }
}
