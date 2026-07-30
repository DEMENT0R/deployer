<?php

namespace Tests\Feature;

use App\Enums\DeployAction;
use App\Enums\DeployStatus;
use App\Enums\UserRole;
use App\Models\Deployment;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeploymentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_log_of_a_past_deployment(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'name' => 'Ada']);
        $instance = Instance::factory()->create();

        $deployment = $this->deployment($instance, $admin, DeployStatus::Failed);
        $deployment->update([
            'output' => "--- git ---\nfatal: boom",
            'exit_code' => 1,
            'started_at' => now()->subMinutes(3),
            'finished_at' => now()->subMinutes(2),
        ]);

        $this->actingAs($admin)
            ->getJson(route('instances.deployments.show', [$instance, $deployment]))
            ->assertOk()
            ->assertJsonPath('output', "--- git ---\nfatal: boom")
            ->assertJsonPath('user', 'Ada')
            ->assertJsonPath('exit_code', 1)
            ->assertJsonPath('duration_seconds', 60);
    }

    public function test_a_deployment_of_another_instance_is_not_found(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create();
        $other = Instance::factory()->create();

        $deployment = $this->deployment($other, $admin, DeployStatus::Success);

        $this->actingAs($admin)
            ->getJson(route('instances.deployments.show', [$instance, $deployment]))
            ->assertNotFound();
    }

    public function test_tester_cannot_read_logs_of_an_unassigned_instance(): void
    {
        $tester = User::factory()->create(['role' => UserRole::Tester]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create();

        $deployment = $this->deployment($instance, $admin, DeployStatus::Success);

        $this->actingAs($tester)
            ->getJson(route('instances.deployments.show', [$instance, $deployment]))
            ->assertForbidden();
    }

    public function test_cancel_marks_a_hanging_deployment_as_failed(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'name' => 'Ada']);
        $instance = Instance::factory()->create();

        $deployment = $this->deployment($instance, $admin, DeployStatus::Running);
        $deployment->update(['output' => 'partial log']);

        $this->actingAs($admin)
            ->post(route('instances.deployments.cancel', [$instance, $deployment]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $deployment->refresh();

        $this->assertSame(DeployStatus::Failed, $deployment->status);
        $this->assertNotNull($deployment->finished_at);
        $this->assertStringContainsString('[CANCELLED]', (string) $deployment->output);
        $this->assertStringContainsString('partial log', (string) $deployment->output);
    }

    public function test_a_finished_deployment_cannot_be_cancelled(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create();

        $deployment = $this->deployment($instance, $admin, DeployStatus::Success);

        $this->actingAs($admin)
            ->post(route('instances.deployments.cancel', [$instance, $deployment]))
            ->assertSessionHasErrors('deploy');

        $this->assertSame(DeployStatus::Success, $deployment->refresh()->status);
    }

    public function test_tester_cannot_cancel_a_deployment_of_an_unassigned_instance(): void
    {
        $tester = User::factory()->create(['role' => UserRole::Tester]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create();

        $deployment = $this->deployment($instance, $admin, DeployStatus::Running);

        $this->actingAs($tester)
            ->post(route('instances.deployments.cancel', [$instance, $deployment]))
            ->assertForbidden();

        $this->assertSame(DeployStatus::Running, $deployment->refresh()->status);
    }

    private function deployment(Instance $instance, User $user, DeployStatus $status): Deployment
    {
        return Deployment::create([
            'instance_id' => $instance->id,
            'user_id' => $user->id,
            'branch' => 'main',
            'action' => DeployAction::Full,
            'status' => $status,
        ]);
    }
}
