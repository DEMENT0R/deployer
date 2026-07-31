<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstanceEnvUpdateTest extends TestCase
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

    public function test_admin_edits_a_value_and_the_rest_of_the_file_survives(): void
    {
        $instance = $this->instanceWithEnv(<<<'ENV'
        # keep me
        APP_ENV=testing
        APP_URL=
        DB_PASSWORD=hunter2
        UNLISTED_KEY=whatever
        ENV);

        $this->actingAs($this->admin())
            ->put(route('admin.instances.env.update', $instance), [
                'values' => ['APP_URL' => 'https://copy.example.com'],
            ])
            ->assertSessionHasNoErrors();

        $contents = $this->env();

        $this->assertStringContainsString('APP_URL=https://copy.example.com', $contents);
        $this->assertStringContainsString('# keep me', $contents);
        $this->assertStringContainsString('APP_ENV=testing', $contents);
        $this->assertStringContainsString('UNLISTED_KEY=whatever', $contents);
        $this->assertStringContainsString('DB_PASSWORD=hunter2', $contents);
    }

    public function test_the_previous_file_is_kept_as_a_backup(): void
    {
        $instance = $this->instanceWithEnv("APP_URL=https://old.example.com\n");

        $this->actingAs($this->admin())
            ->put(route('admin.instances.env.update', $instance), [
                'values' => ['APP_URL' => 'https://new.example.com'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame("APP_URL=https://old.example.com\n", file_get_contents($this->base.'/.env.backup'));
    }

    public function test_an_unchanged_submission_writes_nothing(): void
    {
        $instance = $this->instanceWithEnv("APP_ENV=testing\n");

        $this->actingAs($this->admin())
            ->put(route('admin.instances.env.update', $instance), [
                'values' => ['APP_ENV' => 'testing'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertFileDoesNotExist($this->base.'/.env.backup');
    }

    public function test_an_empty_masked_field_keeps_the_current_secret(): void
    {
        $instance = $this->instanceWithEnv("DB_PASSWORD=hunter2\nAPP_ENV=local\n");

        $this->actingAs($this->admin())
            ->put(route('admin.instances.env.update', $instance), [
                'values' => ['DB_PASSWORD' => '', 'APP_ENV' => 'testing'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertStringContainsString('DB_PASSWORD=hunter2', $this->env());
        $this->assertStringContainsString('APP_ENV=testing', $this->env());
    }

    public function test_a_filled_masked_field_replaces_the_secret(): void
    {
        $instance = $this->instanceWithEnv("DB_PASSWORD=hunter2\n");

        $this->actingAs($this->admin())
            ->put(route('admin.instances.env.update', $instance), [
                'values' => ['DB_PASSWORD' => 'swordfish'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertStringContainsString('DB_PASSWORD=swordfish', $this->env());
        $this->assertStringNotContainsString('hunter2', $this->env());
    }

    public function test_a_key_missing_from_the_file_is_appended(): void
    {
        $instance = $this->instanceWithEnv("APP_ENV=testing\n");

        $this->actingAs($this->admin())
            ->put(route('admin.instances.env.update', $instance), [
                'values' => ['DB_DATABASE' => 'copy_db'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertStringContainsString("APP_ENV=testing\nDB_DATABASE=copy_db\n", $this->env());
    }

    public function test_a_missing_key_left_empty_is_not_added(): void
    {
        $instance = $this->instanceWithEnv("APP_ENV=testing\n");

        $this->actingAs($this->admin())
            ->put(route('admin.instances.env.update', $instance), [
                'values' => ['DB_DATABASE' => ''],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame("APP_ENV=testing\n", $this->env());
    }

    public function test_a_value_needing_quotes_round_trips(): void
    {
        $instance = $this->instanceWithEnv("APP_NAME=Deployer\n");

        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('admin.instances.env.update', $instance), [
                'values' => ['APP_NAME' => 'My App # 2'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertStringContainsString('APP_NAME="My App # 2"', $this->env());

        $response = $this->actingAs($admin)->get(route('admin.instances.env', $instance));
        $variables = collect($response->viewData('page')['props']['env']['variables'])->keyBy('key');

        $this->assertSame('My App # 2', $variables['APP_NAME']['value']);
    }

    public function test_a_key_outside_the_visible_list_is_rejected(): void
    {
        $instance = $this->instanceWithEnv("APP_ENV=testing\nUNLISTED_KEY=whatever\n");

        $this->actingAs($this->admin())
            ->put(route('admin.instances.env.update', $instance), [
                'values' => ['UNLISTED_KEY' => 'hacked'],
            ])
            ->assertSessionHasErrors('values');

        $this->assertStringContainsString('UNLISTED_KEY=whatever', $this->env());
    }

    public function test_a_multiline_value_is_rejected(): void
    {
        $instance = $this->instanceWithEnv("APP_ENV=testing\n");

        $this->actingAs($this->admin())
            ->put(route('admin.instances.env.update', $instance), [
                'values' => ['APP_ENV' => "testing\nDB_PASSWORD=injected"],
            ])
            ->assertSessionHasErrors('values.APP_ENV');

        $this->assertSame("APP_ENV=testing\n", $this->env());
    }

    public function test_a_missing_env_is_reported_instead_of_being_created(): void
    {
        $instance = $this->instanceWithEnv(null);

        $this->actingAs($this->admin())
            ->put(route('admin.instances.env.update', $instance), [
                'values' => ['APP_ENV' => 'testing'],
            ])
            ->assertSessionHasErrors('env');

        $this->assertFileDoesNotExist($this->base.'/.env');
    }

    public function test_env_outside_allowed_prefix_is_not_written(): void
    {
        $instance = $this->instanceWithEnv("APP_ENV=testing\n");

        config(['deployer.allowed_path_prefixes' => ['/var/www']]);
        $instance->update(['allowed_path_prefix' => null]);

        $this->actingAs($this->admin())
            ->put(route('admin.instances.env.update', $instance), [
                'values' => ['APP_ENV' => 'production'],
            ])
            ->assertSessionHasErrors('env');

        $this->assertSame("APP_ENV=testing\n", $this->env());
    }

    public function test_tester_cannot_edit_env(): void
    {
        $instance = $this->instanceWithEnv("APP_ENV=testing\n");

        $tester = User::factory()->create(['role' => UserRole::Tester]);
        $instance->users()->attach($tester);

        $this->actingAs($tester)
            ->put(route('admin.instances.env.update', $instance), [
                'values' => ['APP_ENV' => 'production'],
            ])
            ->assertForbidden();

        $this->assertSame("APP_ENV=testing\n", $this->env());
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function env(): string
    {
        return (string) file_get_contents($this->base.'/.env');
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
