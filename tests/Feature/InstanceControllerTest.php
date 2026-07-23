<?php

namespace Tests\Feature;

use App\Enums\UserRole;
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
}
