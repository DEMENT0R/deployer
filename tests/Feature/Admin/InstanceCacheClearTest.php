<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Exceptions\CacheClearException;
use App\Models\Instance;
use App\Models\User;
use App\Services\InstanceCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstanceCacheClearTest extends TestCase
{
    use RefreshDatabase;

    private ?string $base = null;

    protected function tearDown(): void
    {
        if ($this->base !== null && is_dir($this->base)) {
            @unlink($this->base.'/.env');
            @unlink($this->base.'/.env.backup');
            @rmdir($this->base);
        }

        parent::tearDown();
    }

    public function test_saving_env_clears_the_caches_of_the_target_project(): void
    {
        $instance = $this->instanceWithEnv("DB_DATABASE=old_db\n");

        $this->mock(InstanceCacheService::class)
            ->shouldReceive('clear')
            ->once()
            ->withArgs(fn (Instance $passed) => $passed->is($instance))
            ->andReturn('');

        $this->actingAs($this->admin())
            ->put(route('admin.instances.env.update', $instance), [
                'values' => ['DB_DATABASE' => 'new_db'],
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Updated: DB_DATABASE. Caches cleared.');
    }

    public function test_a_submission_that_changed_nothing_clears_nothing(): void
    {
        $instance = $this->instanceWithEnv("DB_DATABASE=same_db\n");

        $this->mock(InstanceCacheService::class)->shouldNotReceive('clear');

        $this->actingAs($this->admin())
            ->put(route('admin.instances.env.update', $instance), [
                'values' => ['DB_DATABASE' => 'same_db'],
            ])
            ->assertSessionHas('success', 'Nothing to update.');
    }

    /** Файл уже переписан: сорвавшаяся чистка не должна выглядеть как несохранённая правка. */
    public function test_a_failed_clear_is_reported_but_the_env_stays_saved(): void
    {
        $instance = $this->instanceWithEnv("DB_DATABASE=old_db\n");

        $this->mock(InstanceCacheService::class)
            ->shouldReceive('clear')
            ->once()
            ->andThrow(new CacheClearException('Could not find artisan.'));

        $this->actingAs($this->admin())
            ->put(route('admin.instances.env.update', $instance), [
                'values' => ['DB_DATABASE' => 'new_db'],
            ])
            ->assertSessionHasErrors('cache')
            ->assertSessionHas('success', 'Updated: DB_DATABASE.');

        $this->assertStringContainsString('DB_DATABASE=new_db', (string) file_get_contents($this->base.'/.env'));
    }

    public function test_admin_can_clear_caches_with_the_button(): void
    {
        $instance = $this->instanceWithEnv("DB_DATABASE=some_db\n");

        $this->mock(InstanceCacheService::class)
            ->shouldReceive('clear')
            ->once()
            ->andReturn('Configuration cache cleared successfully.');

        $this->actingAs($this->admin())
            ->post(route('admin.instances.caches.clear', $instance))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Caches cleared.');
    }

    public function test_a_failed_clear_from_the_button_is_shown_as_an_error(): void
    {
        $instance = $this->instanceWithEnv("DB_DATABASE=some_db\n");

        $this->mock(InstanceCacheService::class)
            ->shouldReceive('clear')
            ->once()
            ->andThrow(new CacheClearException('No cache command is set for this instance.'));

        $this->actingAs($this->admin())
            ->post(route('admin.instances.caches.clear', $instance))
            ->assertSessionHasErrors('cache');
    }

    public function test_tester_cannot_clear_caches_from_the_admin_route(): void
    {
        $instance = $this->instanceWithEnv("DB_DATABASE=some_db\n");

        $tester = User::factory()->create(['role' => UserRole::Tester]);
        $instance->users()->attach($tester);

        $this->mock(InstanceCacheService::class)->shouldNotReceive('clear');

        $this->actingAs($tester)
            ->post(route('admin.instances.caches.clear', $instance))
            ->assertForbidden();
    }

    public function test_the_env_page_says_whether_there_is_a_cache_command(): void
    {
        $instance = $this->instanceWithEnv("DB_DATABASE=some_db\n");
        $instance->update(['cache_command' => null]);

        $this->actingAs($this->admin())
            ->get(route('admin.instances.env', $instance))
            ->assertInertia(fn ($page) => $page->where('instance.has_cache_command', false));
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function instanceWithEnv(string $contents): Instance
    {
        $this->base = sys_get_temp_dir().'/deployer-cache-'.uniqid();
        mkdir($this->base, 0755, true);
        file_put_contents($this->base.'/.env', $contents);

        config(['deployer.allowed_path_prefixes' => [$this->base]]);

        return Instance::factory()->create([
            'path' => $this->base,
            'allowed_path_prefix' => $this->base,
            'cache_command' => 'php artisan config:clear',
        ]);
    }
}
