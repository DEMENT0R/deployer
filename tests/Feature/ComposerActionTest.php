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

class ComposerActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tester_can_run_composer_on_its_own(): void
    {
        Queue::fake();

        $tester = User::factory()->create(['role' => UserRole::Tester]);
        $instance = Instance::factory()->create([
            'composer_command' => 'composer install --no-dev',
        ]);
        $instance->users()->attach($tester);

        $this->actingAs($tester)
            ->post(route('instances.deploy', $instance), ['action' => DeployAction::Composer->value])
            ->assertSessionHasNoErrors();

        $deployment = $instance->deployments()->sole();

        $this->assertSame(DeployAction::Composer, $deployment->action);
        $this->assertNull($deployment->branch);

        Queue::assertPushed(DeployInstanceJob::class);
    }

    public function test_composer_runs_only_that_step(): void
    {
        $this->assertSame(['composer'], array_map(
            fn ($step) => $step->value,
            DeployAction::Composer->steps(),
        ));
    }

    /**
     * Пустая команда даёт шаг skipped, то есть «успех», в котором ничего не произошло.
     * Для отдельно нажатой кнопки это неотличимо от молчаливого отказа — блокируем раньше.
     */
    public function test_composer_without_a_command_is_refused(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create(['composer_command' => null]);

        $this->actingAs($admin)
            ->post(route('instances.deploy', $instance), ['action' => DeployAction::Composer->value])
            ->assertSessionHasErrors('deploy');

        Queue::assertNothingPushed();
        $this->assertSame(0, $instance->deployments()->count());
    }

    public function test_a_full_deploy_still_skips_an_empty_composer_command(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create(['composer_command' => null]);

        $this->actingAs($admin)
            ->post(route('instances.deploy', $instance), [
                'action' => DeployAction::Full->value,
                'branch' => 'main',
            ])
            ->assertSessionHasNoErrors();

        Queue::assertPushed(DeployInstanceJob::class);
    }

    public function test_the_instance_page_says_whether_there_is_a_command_to_run(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $withCommand = Instance::factory()->create(['composer_command' => 'composer install']);
        $without = Instance::factory()->create(['composer_command' => null]);

        $this->actingAs($admin)
            ->get(route('instances.show', $withCommand))
            ->assertInertia(fn ($page) => $page->where('instance.has_composer_command', true));

        $this->actingAs($admin)
            ->get(route('instances.show', $without))
            ->assertInertia(fn ($page) => $page->where('instance.has_composer_command', false));
    }
}
