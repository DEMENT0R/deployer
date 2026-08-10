<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstanceEnvSiteTitleSyncTest extends TestCase
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

    public function test_saving_env_appends_the_uppercased_instance_name_as_the_title_label(): void
    {
        $instance = $this->instanceWithEnv('sample-instance', "APP_ENV=testing\n");

        $this->actingAs($this->admin())
            ->put(route('admin.instances.env.update', $instance), [
                'values' => ['APP_ENV' => 'production'],
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Updated: APP_ENV, SITE_TITLE_LABEL.');

        $this->assertStringContainsString('SITE_TITLE_LABEL=SAMPLE-INSTANCE', $this->env());
    }

    public function test_saving_env_replaces_a_stale_title_label_and_keeps_the_rest_of_the_file(): void
    {
        $instance = $this->instanceWithEnv(
            'sample-instance',
            "# keep me\nAPP_ENV=testing\nSITE_TITLE_LABEL=OLD-NAME\n",
        );

        $this->actingAs($this->admin())
            ->put(route('admin.instances.env.update', $instance), [
                'values' => ['APP_ENV' => 'production'],
            ])
            ->assertSessionHasNoErrors();

        $contents = $this->env();

        $this->assertStringContainsString('SITE_TITLE_LABEL=SAMPLE-INSTANCE', $contents);
        $this->assertStringNotContainsString('OLD-NAME', $contents);
        $this->assertStringContainsString('# keep me', $contents);
    }

    public function test_a_stale_title_alone_is_enough_to_trigger_a_save(): void
    {
        $instance = $this->instanceWithEnv('sample-instance', "APP_ENV=testing\nSITE_TITLE_LABEL=OLD-NAME\n");

        $this->actingAs($this->admin())
            ->put(route('admin.instances.env.update', $instance), [
                'values' => ['APP_ENV' => 'testing'],
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Updated: SITE_TITLE_LABEL.');

        $this->assertStringContainsString('SITE_TITLE_LABEL=SAMPLE-INSTANCE', $this->env());
    }

    public function test_an_already_matching_title_does_not_force_a_save(): void
    {
        $instance = $this->instanceWithEnv('sample-instance', "APP_ENV=testing\nSITE_TITLE_LABEL=SAMPLE-INSTANCE\n");

        $this->actingAs($this->admin())
            ->put(route('admin.instances.env.update', $instance), [
                'values' => ['APP_ENV' => 'testing'],
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Nothing to update.');

        $this->assertFileDoesNotExist($this->base.'/.env.backup');
    }

    public function test_an_instance_name_with_spaces_is_quoted(): void
    {
        $instance = $this->instanceWithEnv('sample instance', "APP_ENV=testing\n");

        $this->actingAs($this->admin())
            ->put(route('admin.instances.env.update', $instance), [
                'values' => ['APP_ENV' => 'production'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertStringContainsString('SITE_TITLE_LABEL="SAMPLE INSTANCE"', $this->env());
    }

    /** Ключ вычисляется сервером — форма не может подсунуть своё значение мимо названия инстанса. */
    public function test_the_title_label_is_not_directly_editable(): void
    {
        $instance = $this->instanceWithEnv('sample-instance', "APP_ENV=testing\n");

        $this->actingAs($this->admin())
            ->put(route('admin.instances.env.update', $instance), [
                'values' => ['APP_ENV' => 'testing', 'SITE_TITLE_LABEL' => 'HACKED'],
            ])
            ->assertSessionHasErrors('values');

        $this->assertStringNotContainsString('HACKED', $this->env());
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function instanceWithEnv(string $name, string $contents): Instance
    {
        $this->base = sys_get_temp_dir().'/deployer-title-'.uniqid();
        mkdir($this->base, 0755, true);
        file_put_contents($this->base.'/.env', $contents);

        config(['deployer.allowed_path_prefixes' => [$this->base]]);

        return Instance::factory()->create([
            'name' => $name,
            'path' => $this->base,
            'allowed_path_prefix' => $this->base,
        ]);
    }

    private function env(): string
    {
        return (string) file_get_contents($this->base.'/.env');
    }
}
