<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Exceptions\ScreenException;
use App\Models\Instance;
use App\Models\User;
use App\Services\ScreenSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstanceEnvRestartScreenTest extends TestCase
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

    public function test_saving_env_restarts_a_running_serve_session(): void
    {
        $instance = $this->instanceWithEnv("DB_DATABASE=old_db\n");

        $this->mock(ScreenSessionService::class)
            ->shouldReceive('restart')
            ->once()
            ->withArgs(fn (Instance $passed) => $passed->is($instance))
            ->andReturn(true);

        $this->actingAs($this->admin())
            ->put(route('admin.instances.env.update', $instance), [
                'values' => ['DB_DATABASE' => 'new_db'],
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Updated: DB_DATABASE. Session "instance-1" restarted.');
    }

    public function test_saving_env_reports_nothing_extra_when_the_session_was_not_running(): void
    {
        $instance = $this->instanceWithEnv("DB_DATABASE=old_db\n");

        $this->mock(ScreenSessionService::class)
            ->shouldReceive('restart')
            ->once()
            ->andReturn(false);

        $this->actingAs($this->admin())
            ->put(route('admin.instances.env.update', $instance), [
                'values' => ['DB_DATABASE' => 'new_db'],
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Updated: DB_DATABASE.');
    }

    public function test_an_unchanged_submission_does_not_touch_the_session(): void
    {
        $instance = $this->instanceWithEnv("DB_DATABASE=same_db\n");

        $this->mock(ScreenSessionService::class)->shouldNotReceive('restart');

        $this->actingAs($this->admin())
            ->put(route('admin.instances.env.update', $instance), [
                'values' => ['DB_DATABASE' => 'same_db'],
            ])
            ->assertSessionHas('success', 'Nothing to update.');
    }

    /** Файл уже переписан: сорвавшийся перезапуск не должен выглядеть как несохранённая правка. */
    public function test_a_failed_restart_is_reported_but_the_env_stays_saved(): void
    {
        $instance = $this->instanceWithEnv("DB_DATABASE=old_db\n");

        $this->mock(ScreenSessionService::class)
            ->shouldReceive('restart')
            ->once()
            ->andThrow(new ScreenException('Session "instance-1" died right after start.'));

        $this->actingAs($this->admin())
            ->put(route('admin.instances.env.update', $instance), [
                'values' => ['DB_DATABASE' => 'new_db'],
            ])
            ->assertSessionHasErrors('screen')
            ->assertSessionHas('success', 'Updated: DB_DATABASE.');

        $this->assertStringContainsString('DB_DATABASE=new_db', (string) file_get_contents($this->base.'/.env'));
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    /**
     * Имя и SITE_TITLE_LABEL заранее в паре — иначе первое же сохранение синхронизировало бы
     * заголовок и попадало бы в $changed вместе с тем, что проверяет сам тест.
     */
    private function instanceWithEnv(string $contents): Instance
    {
        $this->base = sys_get_temp_dir().'/deployer-screen-env-'.uniqid();
        mkdir($this->base, 0755, true);
        file_put_contents($this->base.'/.env', $contents.'SITE_TITLE_LABEL=SAMPLE-INSTANCE'."\n");

        config(['deployer.allowed_path_prefixes' => [$this->base]]);

        return Instance::factory()->create([
            'name' => 'sample-instance',
            'path' => $this->base,
            'allowed_path_prefix' => $this->base,
            'screen_session' => 'instance-1',
            'serve_port' => 8080,
        ]);
    }
}
