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

class GcActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tester_can_pack_its_own_instance(): void
    {
        Queue::fake();

        $tester = User::factory()->create(['role' => UserRole::Tester]);
        $instance = Instance::factory()->create();
        $instance->users()->attach($tester);

        $this->actingAs($tester)
            ->post(route('instances.deploy', $instance), ['action' => DeployAction::Gc->value])
            ->assertSessionHasNoErrors();

        $deployment = $instance->deployments()->sole();

        $this->assertSame(DeployAction::Gc, $deployment->action);
        $this->assertNull($deployment->branch);

        Queue::assertPushed(DeployInstanceJob::class);
    }

    public function test_a_tester_without_access_cannot_pack(): void
    {
        Queue::fake();

        $stranger = User::factory()->create(['role' => UserRole::Tester]);
        $instance = Instance::factory()->create();

        $this->actingAs($stranger)
            ->post(route('instances.deploy', $instance), ['action' => DeployAction::Gc->value])
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_the_card_knows_its_buttons_are_allowed(): void
    {
        $tester = User::factory()->create(['role' => UserRole::Tester]);
        $instance = Instance::factory()->create();
        $instance->users()->attach($tester);

        $this->actingAs($tester)
            ->get(route('instances.index'))
            ->assertInertia(fn ($page) => $page->where('instances.0.can_deploy', true));
    }

    /** gc только по кнопке: в цепочке деплоя он удлинял бы каждый запуск на минуты. */
    public function test_gc_runs_only_on_its_own(): void
    {
        $this->assertSame(['gc'], array_map(fn ($step) => $step->value, DeployAction::Gc->steps()));

        foreach (DeployAction::cases() as $action) {
            if ($action === DeployAction::Gc) {
                continue;
            }

            $this->assertNotContains('gc', array_map(fn ($step) => $step->value, $action->steps()));
        }
    }
}
