<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
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

    public function test_tester_cannot_view_queues_page(): void
    {
        $tester = User::factory()->create(['role' => UserRole::Tester]);

        $this->actingAs($tester)
            ->get(route('admin.queues.index'))
            ->assertForbidden();
    }
}
