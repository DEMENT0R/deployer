<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use App\Services\ScreenMonitorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScreenControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_screens_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('admin.screens.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Screens/Index')
                ->where('filter', null)
                // ps не должен выполняться на первой отрисовке — список приезжает отдельным запросом.
                ->missing('screen')
            );
    }

    public function test_deferred_request_passes_the_user_filter_to_the_service(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->mock(ScreenMonitorService::class)
            ->shouldReceive('snapshot')
            ->once()
            ->with('mbezrukavnikov')
            ->andReturn(['available' => true, 'sessions' => []]);

        $response = $this->actingAs($admin)
            ->get(route('admin.screens.index', ['user' => 'mbezrukavnikov']), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (new HandleInertiaRequests)->version(request()) ?? '',
                'X-Inertia-Partial-Component' => 'Admin/Screens/Index',
                'X-Inertia-Partial-Data' => 'screen',
            ]);

        // Частичный ответ несёт только запрошенный проп — что фильтр доехал,
        // подтверждает ожидание ->with() на сервисе.
        $response->assertOk();
        $this->assertTrue($response->json('props.screen.available'));
    }

    public function test_overlong_user_filter_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('admin.screens.index', ['user' => str_repeat('a', 65)]))
            ->assertSessionHasErrors('user');
    }

    public function test_tester_cannot_view_screens_page(): void
    {
        $tester = User::factory()->create(['role' => UserRole::Tester]);

        $this->actingAs($tester)
            ->get(route('admin.screens.index'))
            ->assertForbidden();
    }
}
