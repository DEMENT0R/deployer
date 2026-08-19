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

class CacheActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tester_can_clear_caches_on_its_own(): void
    {
        Queue::fake();

        $tester = User::factory()->create(['role' => UserRole::Tester]);
        $instance = Instance::factory()->create([
            'cache_command' => 'php artisan config:clear',
        ]);
        $instance->users()->attach($tester);

        $this->actingAs($tester)
            ->post(route('instances.deploy', $instance), ['action' => DeployAction::Cache->value])
            ->assertSessionHasNoErrors();

        $deployment = $instance->deployments()->sole();

        $this->assertSame(DeployAction::Cache, $deployment->action);
        $this->assertNull($deployment->branch);

        Queue::assertPushed(DeployInstanceJob::class);
    }

    public function test_the_cache_action_runs_only_that_step(): void
    {
        $this->assertSame(['cache'], array_map(
            fn ($step) => $step->value,
            DeployAction::Cache->steps(),
        ));
    }

    /**
     * Порядок здесь — суть шага: с закэшированным конфигом migrate уедет в БД из
     * bootstrap/cache, а не в ту, что лежит в .env.
     */
    public function test_caches_are_cleared_after_composer_and_before_migrations(): void
    {
        $this->assertSame(
            ['backup', 'git', 'composer', 'cache', 'migrate', 'frontend'],
            array_map(fn ($step) => $step->value, DeployAction::Full->steps()),
        );
    }

    public function test_copy_and_rollback_clear_caches_too(): void
    {
        $this->assertSame(
            ['copy', 'composer', 'cache', 'frontend'],
            array_map(fn ($step) => $step->value, DeployAction::Copy->steps()),
        );

        $this->assertSame(
            ['rollback', 'composer', 'cache', 'frontend'],
            array_map(fn ($step) => $step->value, DeployAction::Rollback->steps()),
        );
    }

    public function test_clearing_caches_without_a_command_is_refused(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create(['cache_command' => null]);

        $this->actingAs($admin)
            ->post(route('instances.deploy', $instance), ['action' => DeployAction::Cache->value])
            ->assertSessionHasErrors('deploy');

        Queue::assertNothingPushed();
        $this->assertSame(0, $instance->deployments()->count());
    }

    public function test_a_full_deploy_skips_an_empty_cache_command(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create(['cache_command' => null]);

        $this->actingAs($admin)
            ->post(route('instances.deploy', $instance), [
                'action' => DeployAction::Full->value,
                'branch' => 'main',
            ])
            ->assertSessionHasNoErrors();

        Queue::assertPushed(DeployInstanceJob::class);
    }

    public function test_the_instance_page_says_whether_there_is_a_cache_command(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $withCommand = Instance::factory()->create(['cache_command' => 'php artisan config:clear']);
        $without = Instance::factory()->create(['cache_command' => null]);

        $this->actingAs($admin)
            ->get(route('instances.show', $withCommand))
            ->assertInertia(fn ($page) => $page->where('instance.has_cache_command', true));

        $this->actingAs($admin)
            ->get(route('instances.show', $without))
            ->assertInertia(fn ($page) => $page->where('instance.has_cache_command', false));
    }
}
