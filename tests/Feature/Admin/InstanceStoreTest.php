<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class InstanceStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_instances_cannot_share_a_path(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Instance::factory()->create(['path' => '/var/www/app']);

        $this->actingAs($admin)
            ->post(route('admin.instances.store'), $this->payload(['path' => '/var/www/app']))
            ->assertSessionHasErrors('path');

        $this->assertSame(1, Instance::where('path', '/var/www/app')->count());
    }

    public function test_updating_an_instance_keeps_its_own_path(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create(['path' => '/var/www/app']);

        $this->actingAs($admin)
            ->put(route('admin.instances.update', $instance), $this->payload([
                'path' => '/var/www/app',
                'name' => 'Renamed',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $instance->fresh()->name);
    }

    public function test_duplicate_prefills_the_form_without_path(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $tester = User::factory()->create(['role' => UserRole::Tester]);
        $instance = Instance::factory()->create([
            'name' => 'Staging',
            'path' => '/var/www/app',
            'git_remote' => 'upstream',
        ]);
        $instance->users()->attach($tester);

        $this->actingAs($admin)
            ->get(route('admin.instances.duplicate', $instance))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Instances/Form')
                ->where('instance', null)
                ->where('prefill.name', 'Staging (copy)')
                ->where('prefill.path', '')
                ->where('prefill.git_remote', 'upstream')
                ->where('prefill.tester_ids', [$tester->id])
            );
    }

    public function test_tester_cannot_duplicate(): void
    {
        $tester = User::factory()->create(['role' => UserRole::Tester]);
        $instance = Instance::factory()->create();

        $this->actingAs($tester)
            ->get(route('admin.instances.duplicate', $instance))
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'App',
            'path' => '/var/www/new',
            'platform' => 'linux',
            'git_remote' => 'origin',
            'default_branch' => 'main',
            'migrate_command' => 'php artisan migrate --force',
            'frontend_command' => 'npm ci && npm run build',
            'is_active' => true,
        ], $overrides);
    }
}
