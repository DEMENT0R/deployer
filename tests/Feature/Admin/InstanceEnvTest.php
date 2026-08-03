<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstanceEnvTest extends TestCase
{
    use RefreshDatabase;

    private ?string $base = null;

    protected function tearDown(): void
    {
        if ($this->base !== null && is_dir($this->base)) {
            @unlink($this->base.'/.env');
            @rmdir($this->base);
        }

        parent::tearDown();
    }

    public function test_admin_sees_parsed_env_with_masked_secrets(): void
    {
        $instance = $this->instanceWithEnv(<<<'ENV'
        # comment line
        APP_ENV=testing
        APP_DEBUG=true
        APP_KEY=base64:0123456789abcdefghij
        DB_CONNECTION="mysql"
        DB_PASSWORD=hunter2
        UNLISTED_KEY=whatever
        ENV);

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)
            ->get(route('admin.instances.env', $instance))
            ->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Instances/Env')
            ->where('env.status', 'ok')
            ->where('env.hidden_count', 1)
            ->has('env.file.size')
        );

        $variables = collect($response->viewData('page')['props']['env']['variables'])
            ->keyBy('key');

        $this->assertSame('testing', $variables['APP_ENV']['value']);
        $this->assertSame('mysql', $variables['DB_CONNECTION']['value']);
        $this->assertFalse($variables['MAIL_MAILER']['present']);

        $this->assertTrue($variables['APP_KEY']['masked']);
        $this->assertSame('bas••••••hij', $variables['APP_KEY']['value']);

        $this->assertTrue($variables['DB_PASSWORD']['masked']);
        $this->assertSame('•••••••', $variables['DB_PASSWORD']['value']);

        // Незамаскированные секреты не должны доехать до браузера ни в каком виде.
        $response->assertDontSee('hunter2');
        $response->assertDontSee('UNLISTED_KEY');
    }

    public function test_missing_env_is_reported_instead_of_failing(): void
    {
        $instance = $this->instanceWithEnv(null);

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('admin.instances.env', $instance))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('env.status', 'missing')
                ->where('env.file', null)
            );
    }

    public function test_env_outside_allowed_prefix_is_not_read(): void
    {
        $instance = $this->instanceWithEnv('APP_ENV=testing');

        config(['deployer.allowed_path_prefixes' => ['/var/www']]);
        $instance->update(['allowed_path_prefix' => null]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('admin.instances.env', $instance))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('env.status', 'path_error'));
    }

    public function test_admin_instance_table_reports_the_database_name(): void
    {
        $instance = $this->instanceWithEnv("APP_ENV=testing\nDB_DATABASE=stage_alpha\n");
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        // Первая отрисовка не читает .env — колонка приезжает отдельным запросом.
        $this->actingAs($admin)
            ->get(route('admin.instances.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Instances/Index')
                ->missing('databases')
            );

        $databases = $this->actingAs($admin)
            ->get(route('admin.instances.index'), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (new HandleInertiaRequests)->version(request()) ?? '',
                'X-Inertia-Partial-Component' => 'Admin/Instances/Index',
                'X-Inertia-Partial-Data' => 'databases',
            ])
            ->assertOk()
            ->json('props.databases');

        $this->assertSame('stage_alpha', $databases[$instance->id]);
    }

    public function test_tester_cannot_view_env_page(): void
    {
        $instance = $this->instanceWithEnv('APP_ENV=testing');

        $tester = User::factory()->create(['role' => UserRole::Tester]);
        $instance->users()->attach($tester);

        $this->actingAs($tester)
            ->get(route('admin.instances.env', $instance))
            ->assertForbidden();
    }

    private function instanceWithEnv(?string $contents): Instance
    {
        $this->base = sys_get_temp_dir().'/deployer-env-'.uniqid();
        mkdir($this->base, 0755, true);

        if ($contents !== null) {
            file_put_contents($this->base.'/.env', $contents);
        }

        config(['deployer.allowed_path_prefixes' => [$this->base]]);

        return Instance::factory()->create([
            'path' => $this->base,
            'allowed_path_prefix' => $this->base,
        ]);
    }
}
