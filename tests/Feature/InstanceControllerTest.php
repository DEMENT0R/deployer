<?php

namespace Tests\Feature;

use App\Enums\DeployAction;
use App\Enums\DeployStatus;
use App\Enums\UserRole;
use App\Models\Deployment;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class InstanceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_reports_why_the_branch_list_is_empty(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create(['path' => '/var/www/does-not-exist']);

        $this->actingAs($admin)
            ->get(route('instances.show', $instance))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Instances/Show')
                ->where('branches', [])
                ->whereNot('branchError', null)
            );
    }

    public function test_show_lists_deployment_history_newest_first(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'name' => 'Ada']);
        $instance = Instance::factory()->create(['path' => '/var/www/does-not-exist']);

        $older = $this->deployment($instance, $admin, 'main', DeployStatus::Success);
        $older->update([
            'started_at' => now()->subMinutes(10),
            'finished_at' => now()->subMinutes(8),
            'output' => 'secret build log',
        ]);

        $newer = $this->deployment($instance, $admin, 'feature/x', DeployStatus::Failed);
        $newer->update(['exit_code' => 1]);

        $this->actingAs($admin)
            ->get(route('instances.show', $instance))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('deployments', 2)
                ->where('deployments.0.branch', 'feature/x')
                ->where('deployments.0.status', 'failed')
                ->where('deployments.0.exit_code', 1)
                ->where('deployments.1.branch', 'main')
                ->where('deployments.1.user', 'Ada')
                ->where('deployments.1.duration_seconds', 120)
                // Логи в историю не тянем: там столько текста, сколько выдал деплой.
                ->missing('deployments.1.output')
            );
    }

    public function test_show_defers_the_working_tree_snapshot(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create(['path' => '/var/www/does-not-exist']);

        // Первый заход отдаёт страницу без git-статуса — он приезжает отдельным запросом.
        $response = $this->actingAs($admin)->get(route('instances.show', $instance));

        $response->assertInertia(fn (AssertableInertia $page) => $page->missing('gitStatus'));

        // Дозапрос отдаёт JSON, а не страницу, поэтому проверяем тело напрямую.
        $this->actingAs($admin)
            ->get(route('instances.show', $instance), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $response->viewData('page')['version'],
                'X-Inertia-Partial-Component' => 'Instances/Show',
                'X-Inertia-Partial-Data' => 'gitStatus',
            ])
            ->assertOk()
            ->assertJsonPath('props.gitStatus.status', 'path_error')
            ->assertJsonMissingPath('props.deployment');
    }

    public function test_show_marks_an_abandoned_deployment_as_stale(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create(['path' => '/var/www/does-not-exist']);

        $deployment = $this->deployment($instance, $admin, 'main', DeployStatus::Running);
        $deployment->forceFill([
            'updated_at' => now()->subSeconds(Deployment::staleAfter() + 60),
        ])->saveQuietly();

        $this->actingAs($admin)
            ->get(route('instances.show', $instance))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('deployment.status', 'running')
                ->where('deployment.is_stale', true)
            );
    }

    public function test_show_flags_a_deployment_stuck_in_the_queue(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create(['path' => '/var/www/does-not-exist']);

        $deployment = $this->deployment($instance, $admin, 'main', DeployStatus::Pending);
        $deployment->forceFill([
            'created_at' => now()->subSeconds((int) config('deployer.queue_warn_after') + 5),
        ])->saveQuietly();

        $this->actingAs($admin)
            ->get(route('instances.show', $instance))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('deployment.queue_stuck', true)
                ->where('deployment.is_stale', false)
            );
    }

    private function deployment(Instance $instance, User $user, string $branch, DeployStatus $status): Deployment
    {
        return Deployment::create([
            'instance_id' => $instance->id,
            'user_id' => $user->id,
            'branch' => $branch,
            'action' => DeployAction::Full,
            'status' => $status,
        ]);
    }
}
