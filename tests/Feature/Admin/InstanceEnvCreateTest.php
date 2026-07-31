<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstanceEnvCreateTest extends TestCase
{
    use RefreshDatabase;

    private ?string $base = null;

    protected function tearDown(): void
    {
        if ($this->base !== null && is_dir($this->base)) {
            @unlink($this->base.'/.env');
            @unlink($this->base.'/.env.example');
            @rmdir($this->base);
        }

        parent::tearDown();
    }

    public function test_admin_creates_an_empty_env(): void
    {
        $instance = $this->instanceWithFiles();

        $this->actingAs($this->admin())
            ->post(route('admin.instances.env.store', $instance), ['source' => 'blank'])
            ->assertSessionHasNoErrors();

        $this->assertSame('', file_get_contents($this->base.'/.env'));
    }

    public function test_admin_creates_an_env_from_the_example(): void
    {
        $example = "APP_ENV=local\nDB_DATABASE=\n";
        $instance = $this->instanceWithFiles(example: $example);

        $this->actingAs($this->admin())
            ->post(route('admin.instances.env.store', $instance), ['source' => 'example'])
            ->assertSessionHasNoErrors();

        $this->assertSame($example, file_get_contents($this->base.'/.env'));
    }

    public function test_an_existing_env_is_never_overwritten(): void
    {
        $instance = $this->instanceWithFiles(env: "APP_ENV=production\n", example: "APP_ENV=local\n");

        $this->actingAs($this->admin())
            ->post(route('admin.instances.env.store', $instance), ['source' => 'example'])
            ->assertSessionHasErrors('env');

        $this->assertSame("APP_ENV=production\n", file_get_contents($this->base.'/.env'));
    }

    public function test_creating_from_a_missing_example_is_reported(): void
    {
        $instance = $this->instanceWithFiles();

        $this->actingAs($this->admin())
            ->post(route('admin.instances.env.store', $instance), ['source' => 'example'])
            ->assertSessionHasErrors('env');

        $this->assertFileDoesNotExist($this->base.'/.env');
    }

    public function test_an_unknown_source_is_rejected(): void
    {
        $instance = $this->instanceWithFiles();

        $this->actingAs($this->admin())
            ->post(route('admin.instances.env.store', $instance), ['source' => '../../etc/passwd'])
            ->assertSessionHasErrors('source');

        $this->assertFileDoesNotExist($this->base.'/.env');
    }

    public function test_the_page_reports_whether_an_example_is_available(): void
    {
        $admin = $this->admin();
        $instance = $this->instanceWithFiles(example: "APP_ENV=local\n");

        $this->actingAs($admin)
            ->get(route('admin.instances.env', $instance))
            ->assertInertia(fn ($page) => $page
                ->where('env.status', 'missing')
                ->where('env.example_available', true)
            );

        @unlink($this->base.'/.env.example');

        $this->actingAs($admin)
            ->get(route('admin.instances.env', $instance))
            ->assertInertia(fn ($page) => $page->where('env.example_available', false));
    }

    public function test_tester_cannot_create_env(): void
    {
        $instance = $this->instanceWithFiles();

        $tester = User::factory()->create(['role' => UserRole::Tester]);
        $instance->users()->attach($tester);

        $this->actingAs($tester)
            ->post(route('admin.instances.env.store', $instance), ['source' => 'blank'])
            ->assertForbidden();

        $this->assertFileDoesNotExist($this->base.'/.env');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function instanceWithFiles(?string $env = null, ?string $example = null): Instance
    {
        $this->base = sys_get_temp_dir().'/deployer-env-'.uniqid();
        mkdir($this->base, 0755, true);

        if ($env !== null) {
            file_put_contents($this->base.'/.env', $env);
        }

        if ($example !== null) {
            file_put_contents($this->base.'/.env.example', $example);
        }

        config(['deployer.allowed_path_prefixes' => [$this->base]]);

        return Instance::factory()->create([
            'path' => $this->base,
            'allowed_path_prefix' => $this->base,
        ]);
    }
}
