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

class TestActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tester_can_run_the_suite_on_its_own_instance(): void
    {
        Queue::fake();

        $tester = User::factory()->create(['role' => UserRole::Tester]);
        $instance = Instance::factory()->create(['test_command' => 'php artisan test']);
        $instance->users()->attach($tester);

        $this->actingAs($tester)
            ->post(route('instances.deploy', $instance), ['action' => DeployAction::Test->value])
            ->assertSessionHasNoErrors();

        $deployment = $instance->deployments()->sole();

        $this->assertSame(DeployAction::Test, $deployment->action);
        $this->assertNull($deployment->branch);

        Queue::assertPushed(DeployInstanceJob::class);
    }

    public function test_the_test_action_runs_only_that_step(): void
    {
        $this->assertSame(['test'], array_map(
            fn ($step) => $step->value,
            DeployAction::Test->steps(),
        ));
    }

    /**
     * Суть шага: красный сюит не должен ронять деплой уже после того, как код на стенде,
     * а тесты целевого проекта ходят в его базу.
     */
    public function test_no_deploy_chain_runs_the_suite(): void
    {
        foreach (DeployAction::cases() as $action) {
            if ($action === DeployAction::Test) {
                continue;
            }

            $steps = array_map(fn ($step) => $step->value, $action->steps());

            $this->assertNotContains('test', $steps, "The {$action->value} action must not run tests.");
        }
    }

    public function test_running_the_suite_without_a_command_is_refused(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create(['test_command' => null]);

        $this->actingAs($admin)
            ->post(route('instances.deploy', $instance), ['action' => DeployAction::Test->value])
            ->assertSessionHasErrors('deploy');

        Queue::assertNothingPushed();
        $this->assertSame(0, $instance->deployments()->count());
    }

    public function test_the_instance_page_says_whether_there_is_a_test_command(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $withCommand = Instance::factory()->create(['test_command' => 'php artisan test']);
        $without = Instance::factory()->create(['test_command' => null]);

        $this->actingAs($admin)
            ->get(route('instances.show', $withCommand))
            ->assertInertia(fn ($page) => $page->where('instance.has_test_command', true));

        $this->actingAs($admin)
            ->get(route('instances.show', $without))
            ->assertInertia(fn ($page) => $page->where('instance.has_test_command', false));
    }
}
