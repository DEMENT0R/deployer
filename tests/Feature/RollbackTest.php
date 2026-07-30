<?php

namespace Tests\Feature;

use App\Enums\DeployAction;
use App\Enums\DeployStatus;
use App\Enums\UserRole;
use App\Jobs\DeployInstanceJob;
use App\Models\Deployment;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RollbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_rollback_targets_the_previous_deploys_commit(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create();

        $this->deployment($instance, $admin, DeployAction::Full, DeployStatus::Success, 'aaaaaaa');
        $this->deployment($instance, $admin, DeployAction::Full, DeployStatus::Success, 'bbbbbbb');

        $this->actingAs($admin)
            ->post(route('instances.rollback', $instance))
            ->assertSessionHasNoErrors();

        $rollback = $instance->deployments()->where('action', DeployAction::Rollback)->sole();
        // Откатываемся к состоянию до последнего успешного деплоя.
        $this->assertSame('bbbbbbb', $rollback->branch);
        $this->assertSame(DeployStatus::Pending, $rollback->status);

        Queue::assertPushed(DeployInstanceJob::class);
    }

    public function test_rollback_without_history_is_rejected(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create();

        $this->actingAs($admin)
            ->post(route('instances.rollback', $instance))
            ->assertSessionHasErrors('deploy');

        Queue::assertNothingPushed();
    }

    public function test_rollback_ignores_deploys_without_a_recorded_commit(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create();

        // Успешный деплой, но без зафиксированного commit_before (например, до появления фичи).
        $this->deployment($instance, $admin, DeployAction::Full, DeployStatus::Success, null);

        $this->actingAs($admin)
            ->post(route('instances.rollback', $instance))
            ->assertSessionHasErrors('deploy');

        Queue::assertNothingPushed();
    }

    public function test_tester_without_access_cannot_roll_back(): void
    {
        $tester = User::factory()->create(['role' => UserRole::Tester]);
        $instance = Instance::factory()->create();

        $this->actingAs($tester)
            ->post(route('instances.rollback', $instance))
            ->assertForbidden();
    }

    private function deployment(
        Instance $instance,
        User $user,
        DeployAction $action,
        DeployStatus $status,
        ?string $commitBefore,
    ): Deployment {
        return Deployment::create([
            'instance_id' => $instance->id,
            'user_id' => $user->id,
            'branch' => 'main',
            'commit_before' => $commitBefore,
            'action' => $action,
            'status' => $status,
        ]);
    }
}
