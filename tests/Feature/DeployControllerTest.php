<?php

namespace Tests\Feature;

use App\Enums\DeployAction;
use App\Enums\UserRole;
use App\Jobs\DeployInstanceJob;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeployControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_deploy_requires_valid_branch_for_full_action(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create();

        $this->actingAs($admin)
            ->post(route('instances.deploy', $instance), [
                'action' => DeployAction::Full->value,
                'branch' => 'invalid branch!',
            ])
            ->assertSessionHasErrors('branch');

        Queue::assertNothingPushed();
    }

    public function test_authorized_user_can_queue_full_deploy(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create();

        $this->actingAs($admin)
            ->post(route('instances.deploy', $instance), [
                'action' => DeployAction::Full->value,
                'branch' => 'feature/login',
            ])
            ->assertRedirect();

        Queue::assertPushed(DeployInstanceJob::class);

        $this->assertDatabaseHas('deployments', [
            'instance_id' => $instance->id,
            'user_id' => $admin->id,
            'branch' => 'feature/login',
            'action' => DeployAction::Full->value,
        ]);
    }

    public function test_tester_cannot_deploy_unassigned_instance(): void
    {
        Queue::fake();

        $tester = User::factory()->create(['role' => UserRole::Tester]);
        $instance = Instance::factory()->create();

        $this->actingAs($tester)
            ->post(route('instances.deploy', $instance), [
                'action' => DeployAction::Migrate->value,
            ])
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_migrate_action_does_not_require_branch(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create();

        $this->actingAs($admin)
            ->post(route('instances.deploy', $instance), [
                'action' => DeployAction::Migrate->value,
            ])
            ->assertRedirect();

        Queue::assertPushed(DeployInstanceJob::class);
    }
}
