<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class FlashMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_flash_message_reaches_the_next_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create(['path' => '/var/www/app']);

        $this->actingAs($admin)
            ->followingRedirects()
            ->put(route('admin.instances.update', $instance), [
                'name' => 'Renamed',
                'path' => '/var/www/app',
                'platform' => 'linux',
                'git_remote' => 'origin',
                'default_branch' => 'main',
                'migrate_command' => 'php artisan migrate --force',
                'frontend_command' => 'npm ci && npm run build',
                'is_active' => true,
            ])
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('flash.success', 'Instance updated.')
            );
    }

    public function test_a_plain_page_carries_no_message(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('admin.instances.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('flash.success', null)
            );
    }

    /**
     * Флеш живёт один запрос, а поллинг деплоя ходит частичными перезагрузками каждые 3 секунды.
     * Попади он в такой ответ — сообщение сгорало бы, не доехав до экрана. Поэтому в пропсах
     * частичного ответа `flash` быть не должно: замыкание для незапрошенного ключа не считается.
     */
    public function test_a_partial_reload_leaves_the_message_alone(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Instance::factory()->create();

        // Без совпадающей версии Inertia ответит 409 вместо частичной перезагрузки.
        $version = (new HandleInertiaRequests)->version(request());

        $props = $this->actingAs($admin)
            ->withSession(['success' => 'Deployment queued.'])
            ->get(route('admin.instances.index'), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $version,
                'X-Inertia-Partial-Component' => 'Admin/Instances/Index',
                'X-Inertia-Partial-Data' => 'instances',
            ])
            ->assertOk()
            ->json('props');

        $this->assertArrayHasKey('instances', $props);
        $this->assertArrayNotHasKey('flash', $props);
    }
}
