<?php

namespace Tests\Feature\Admin;

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

class InstanceCloneTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_queues_a_clone_deployment(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create(['repository_url' => 'git@github.com:org/repo.git']);

        $this->actingAs($admin)
            ->post(route('admin.instances.clone', $instance))
            ->assertRedirect(route('instances.show', $instance));

        $deployment = $instance->deployments()->sole();
        $this->assertSame(DeployAction::Clone, $deployment->action);
        $this->assertSame(DeployStatus::Pending, $deployment->status);

        Queue::assertPushed(DeployInstanceJob::class);
    }

    public function test_clone_requires_a_repository_url(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create(['repository_url' => null]);

        $this->actingAs($admin)
            ->post(route('admin.instances.clone', $instance))
            ->assertSessionHasErrors('clone');

        Queue::assertNothingPushed();
        $this->assertSame(0, $instance->deployments()->count());
    }

    public function test_clone_is_blocked_while_a_deployment_runs(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create(['repository_url' => 'git@github.com:org/repo.git']);

        Deployment::create([
            'instance_id' => $instance->id,
            'user_id' => $admin->id,
            'action' => DeployAction::Full,
            'status' => DeployStatus::Running,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.instances.clone', $instance))
            ->assertSessionHasErrors('clone');

        Queue::assertNothingPushed();
    }

    public function test_tester_cannot_trigger_clone(): void
    {
        $tester = User::factory()->create(['role' => UserRole::Tester]);
        $instance = Instance::factory()->create(['repository_url' => 'git@github.com:org/repo.git']);
        $instance->users()->attach($tester);

        $this->actingAs($tester)
            ->post(route('admin.instances.clone', $instance))
            ->assertForbidden();
    }

    public function test_clone_action_is_rejected_on_the_tester_deploy_endpoint(): void
    {
        $tester = User::factory()->create(['role' => UserRole::Tester]);
        $instance = Instance::factory()->create();
        $instance->users()->attach($tester);

        $this->actingAs($tester)
            ->post(route('instances.deploy', $instance), ['action' => 'clone'])
            ->assertSessionHasErrors('action');

        $this->assertSame(0, $instance->deployments()->count());
    }
}
