<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstancePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_any_instance(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create();

        $this->actingAs($admin)
            ->get(route('instances.show', $instance))
            ->assertOk();
    }

    public function test_tester_can_view_assigned_instance(): void
    {
        $tester = User::factory()->create(['role' => UserRole::Tester]);
        $instance = Instance::factory()->create();
        $instance->users()->attach($tester);

        $this->actingAs($tester)
            ->get(route('instances.show', $instance))
            ->assertOk();
    }

    public function test_tester_cannot_view_unassigned_instance(): void
    {
        $tester = User::factory()->create(['role' => UserRole::Tester]);
        $instance = Instance::factory()->create();

        $this->actingAs($tester)
            ->get(route('instances.show', $instance))
            ->assertForbidden();
    }

    public function test_tester_cannot_access_admin_routes(): void
    {
        $tester = User::factory()->create(['role' => UserRole::Tester]);

        $this->actingAs($tester)
            ->get(route('admin.instances.index'))
            ->assertForbidden();
    }

    public function test_admin_can_access_admin_routes(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('admin.instances.index'))
            ->assertOk();
    }
}
