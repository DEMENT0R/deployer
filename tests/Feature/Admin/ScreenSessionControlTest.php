<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Exceptions\ScreenException;
use App\Models\Instance;
use App\Models\User;
use App\Services\ScreenSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScreenSessionControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_screens_page_lists_instances_that_can_be_started(): void
    {
        $servable = Instance::factory()->create([
            'name' => 'MB 1',
            'screen_session' => 'laravel-test',
            'serve_port' => 8080,
        ]);
        Instance::factory()->create(['screen_session' => null, 'serve_port' => null]);

        $this->actingAs($this->admin())
            ->get(route('admin.screens.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Screens/Index')
                ->has('instances', 1)
                ->where('instances.0.id', $servable->id)
                ->where('instances.0.screen_session', 'laravel-test')
                ->where('instances.0.serve_port', 8080)
            );
    }

    public function test_admin_can_start_a_configured_instance(): void
    {
        $instance = Instance::factory()->create([
            'screen_session' => 'alpha-test',
            'serve_port' => 8081,
        ]);

        $this->mock(ScreenSessionService::class)
            ->shouldReceive('start')
            ->once()
            ->withArgs(fn (Instance $passed) => $passed->is($instance))
            ->andReturn('alpha-test');

        $this->actingAs($this->admin())
            ->post(route('admin.screens.store'), ['instance_id' => $instance->id])
            ->assertRedirect()
            ->assertSessionHas('success', 'Session "alpha-test" started.');
    }

    public function test_a_failed_start_is_shown_as_an_error_not_a_success(): void
    {
        $instance = Instance::factory()->create([
            'screen_session' => 'alpha-test',
            'serve_port' => 8081,
        ]);

        $this->mock(ScreenSessionService::class)
            ->shouldReceive('start')
            ->once()
            ->andThrow(new ScreenException('Session "alpha-test" died right after start.'));

        $this->actingAs($this->admin())
            ->post(route('admin.screens.store'), ['instance_id' => $instance->id])
            ->assertSessionHasErrors(['screen' => 'Session "alpha-test" died right after start.'])
            ->assertSessionMissing('success');
    }

    public function test_start_requires_an_existing_instance(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.screens.store'), ['instance_id' => 999])
            ->assertSessionHasErrors('instance_id');
    }

    public function test_admin_can_stop_a_session_by_name(): void
    {
        $this->mock(ScreenSessionService::class)
            ->shouldReceive('stop')
            ->once()
            ->with('alpha-test');

        $this->actingAs($this->admin())
            ->delete(route('admin.screens.destroy'), ['session' => 'alpha-test'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Session "alpha-test" stopped.');
    }

    public function test_stopping_someone_elses_session_reports_the_screen_error(): void
    {
        $this->mock(ScreenSessionService::class)
            ->shouldReceive('stop')
            ->once()
            ->andThrow(new ScreenException('No screen session found.'));

        $this->actingAs($this->admin())
            ->delete(route('admin.screens.destroy'), ['session' => 'backups'])
            ->assertSessionHasErrors(['screen' => 'No screen session found.']);
    }

    public function test_session_name_with_whitespace_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->delete(route('admin.screens.destroy'), ['session' => 'alpha test'])
            ->assertSessionHasErrors('session');
    }

    public function test_tester_cannot_start_or_stop_sessions(): void
    {
        $tester = User::factory()->create(['role' => UserRole::Tester]);
        $instance = Instance::factory()->create([
            'screen_session' => 'alpha-test',
            'serve_port' => 8081,
        ]);

        $this->actingAs($tester)
            ->post(route('admin.screens.store'), ['instance_id' => $instance->id])
            ->assertForbidden();

        $this->actingAs($tester)
            ->delete(route('admin.screens.destroy'), ['session' => 'alpha-test'])
            ->assertForbidden();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }
}
