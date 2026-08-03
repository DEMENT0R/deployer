<?php

namespace Tests\Feature\Admin;

use App\Enums\DeployAction;
use App\Enums\DeployStatus;
use App\Enums\UserRole;
use App\Models\Deployment;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_queues_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('admin.queues.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Queues/Index')
                ->has('summary')
                ->has('jobs')
                ->has('failed_jobs')
            );
    }

    /**
     * Убитый воркер оставляет строку в running навсегда. Страница инстанса зовёт такую
     * Abandoned, а очереди показывали её живой — из-за этого деплой «висел» здесь неделями.
     */
    public function test_a_deployment_nobody_writes_to_anymore_is_marked_abandoned(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create();

        $abandoned = Deployment::create([
            'instance_id' => $instance->id,
            'user_id' => $admin->id,
            'action' => DeployAction::Composer,
            'status' => DeployStatus::Running,
        ]);

        $alive = Deployment::create([
            'instance_id' => $instance->id,
            'user_id' => $admin->id,
            'action' => DeployAction::Migrate,
            'status' => DeployStatus::Running,
        ]);

        // updated_at правим запросом: обычный save() поставил бы текущее время обратно.
        Deployment::query()->whereKey($abandoned->id)->update([
            'updated_at' => now()->subSeconds(Deployment::staleAfter() + 60),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.queues.index'))->assertOk();

        // По id, а не по позиции: обе строки созданы в одну секунду, порядок между ними не определён.
        $rows = collect($response->viewData('page')['props']['active_deployments'])->keyBy('id');

        $this->assertCount(2, $rows);
        $this->assertTrue($rows[$abandoned->id]['is_stale']);
        $this->assertFalse($rows[$alive->id]['is_stale']);
    }

    public function test_tester_cannot_view_queues_page(): void
    {
        $tester = User::factory()->create(['role' => UserRole::Tester]);

        $this->actingAs($tester)
            ->get(route('admin.queues.index'))
            ->assertForbidden();
    }
}
