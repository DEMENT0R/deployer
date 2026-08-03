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

    public function test_screen_session_and_serve_port_are_saved_together(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->post(route('admin.instances.store'), $this->payload([
                'screen_session' => 'laravel-test',
                'serve_port' => '8080',
            ]))
            ->assertSessionHasNoErrors();

        $instance = Instance::where('path', '/var/www/new')->firstOrFail();
        $this->assertSame('laravel-test', $instance->screen_session);
        $this->assertSame(8080, $instance->serve_port);
    }

    public function test_a_serve_port_without_a_session_name_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->post(route('admin.instances.store'), $this->payload(['serve_port' => '8080']))
            ->assertSessionHasErrors('screen_session');
    }

    public function test_two_instances_cannot_share_a_session_name_or_a_port(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Instance::factory()->create(['screen_session' => 'laravel-test', 'serve_port' => 8080]);

        $this->actingAs($admin)
            ->post(route('admin.instances.store'), $this->payload([
                'screen_session' => 'laravel-test',
                'serve_port' => '8080',
            ]))
            ->assertSessionHasErrors(['screen_session', 'serve_port']);
    }

    public function test_a_session_name_with_shell_characters_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->post(route('admin.instances.store'), $this->payload([
                'screen_session' => 'test; rm -rf /',
                'serve_port' => '8080',
            ]))
            ->assertSessionHasErrors('screen_session');
    }

    public function test_duplicate_does_not_prefill_the_session_name_or_port(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create([
            'screen_session' => 'laravel-test',
            'serve_port' => 8080,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.instances.duplicate', $instance))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('prefill.screen_session', null)
                ->where('prefill.serve_port', null)
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
