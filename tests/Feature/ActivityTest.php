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

class ActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_tester_sees_only_deployments_of_their_instances(): void
    {
        $tester = User::factory()->create(['role' => UserRole::Tester]);

        $mine = Instance::factory()->create(['name' => 'Mine']);
        $mine->users()->attach($tester);
        $this->deployment($mine, $tester, 'feature/mine');

        $foreign = Instance::factory()->create(['name' => 'Foreign']);
        $this->deployment($foreign, $tester, 'feature/foreign');

        $response = $this->actingAs($tester)
            ->get(route('activity.index'))
            ->assertOk();

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Activity/Index')
            ->has('deployments.data', 1)
            ->where('deployments.data.0.instance.name', 'Mine')
            ->where('deployments.data.0.branch', 'feature/mine')
        );

        // Чужой инстанс не должен просвечивать даже названием ветки.
        $response->assertDontSee('feature/foreign');
        $response->assertDontSee('Foreign');
    }

    public function test_admin_sees_deployments_across_instances(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $first = Instance::factory()->create();
        $second = Instance::factory()->create();
        $this->deployment($first, $admin, 'main');
        $this->deployment($second, $admin, 'develop');

        $this->actingAs($admin)
            ->get(route('activity.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('deployments.data', 2));
    }

    public function test_feed_can_be_filtered_by_status(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create();

        $this->deployment($instance, $admin, 'main', DeployStatus::Success);
        $this->deployment($instance, $admin, 'broken', DeployStatus::Failed);

        $this->actingAs($admin)
            ->get(route('activity.index', ['status' => 'failed']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('deployments.data', 1)
                ->where('deployments.data.0.branch', 'broken')
                ->where('filters.status', 'failed')
            );
    }

    public function test_unknown_status_filter_is_ignored(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create();
        $this->deployment($instance, $admin, 'main');

        $this->actingAs($admin)
            ->get(route('activity.index', ['status' => 'bogus']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('deployments.data', 1)
                ->where('filters.status', null)
            );
    }

    public function test_feed_does_not_ship_deploy_logs(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create();

        $deployment = $this->deployment($instance, $admin, 'main');
        $deployment->update(['output' => 'secret build log']);

        $this->actingAs($admin)
            ->get(route('activity.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->missing('deployments.data.0.output'))
            ->assertDontSee('secret build log');
    }

    public function test_guest_cannot_see_the_feed(): void
    {
        $this->get(route('activity.index'))->assertRedirect(route('login'));
    }

    private function deployment(
        Instance $instance,
        User $user,
        string $branch,
        DeployStatus $status = DeployStatus::Success,
    ): Deployment {
        return Deployment::create([
            'instance_id' => $instance->id,
            'user_id' => $user->id,
            'branch' => $branch,
            'action' => DeployAction::Full,
            'status' => $status,
        ]);
    }
}
