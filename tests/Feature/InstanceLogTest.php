<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class InstanceLogTest extends TestCase
{
    use RefreshDatabase;

    private ?string $base = null;

    protected function tearDown(): void
    {
        if ($this->base !== null) {
            File::deleteDirectory($this->base);
        }

        parent::tearDown();
    }

    public function test_a_tester_sees_the_tail_of_the_stand_log(): void
    {
        config(['deployer.log_tail_lines' => 3]);

        $instance = $this->instanceWithLog(implode("\n", ['one', 'two', 'three', 'four', 'five'])."\n");
        $tester = User::factory()->create(['role' => UserRole::Tester]);
        $instance->users()->attach($tester);

        $response = $this->actingAs($tester)
            ->getJson(route('instances.log.show', $instance))
            ->assertOk();

        $this->assertSame(['three', 'four', 'five'], $response->json('lines'));
        $this->assertTrue($response->json('truncated'));
        $this->assertTrue($response->json('exists'));
    }

    public function test_a_missing_log_is_not_an_error(): void
    {
        $instance = $this->instanceWithLog(null);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->getJson(route('instances.log.show', $instance))
            ->assertOk()
            ->assertJson(['exists' => false, 'lines' => []]);
    }

    public function test_a_tester_without_access_sees_nothing(): void
    {
        $instance = $this->instanceWithLog("boom\n");
        $stranger = User::factory()->create(['role' => UserRole::Tester]);

        $this->actingAs($stranger)
            ->getJson(route('instances.log.show', $instance))
            ->assertForbidden();
    }

    /** Файл обнуляем, а не удаляем: в удалённый php-fpm продолжит писать открытым дескриптором. */
    public function test_clearing_empties_the_file_in_place(): void
    {
        $instance = $this->instanceWithLog("boom\n");
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->deleteJson(route('instances.log.destroy', $instance))
            ->assertOk()
            ->assertJson(['exists' => true, 'lines' => []]);

        $this->assertFileExists($this->base.'/storage/logs/laravel.log');
        $this->assertSame('', file_get_contents($this->base.'/storage/logs/laravel.log'));
    }

    /** При канале daily растут ротированные файлы: старые удаляем, свежие — только обнуляем. */
    public function test_clearing_sweeps_rotated_logs(): void
    {
        $instance = $this->instanceWithLog("boom\n");
        $logs = $this->base.'/storage/logs';
        file_put_contents($logs.'/laravel-2026-01-01.log', str_repeat('x', 1000));
        touch($logs.'/laravel-2026-01-01.log', time() - 3 * 86400);
        file_put_contents($logs.'/laravel-today.log', 'fresh');
        file_put_contents($logs.'/notes.txt', 'keep');
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->deleteJson(route('instances.log.destroy', $instance))
            ->assertOk()
            ->assertJson(['cleared' => ['emptied' => 2, 'deleted' => 1, 'freed' => 1010]]);

        $this->assertFileDoesNotExist($logs.'/laravel-2026-01-01.log');
        $this->assertSame('', file_get_contents($logs.'/laravel-today.log'));
        $this->assertSame('', file_get_contents($logs.'/laravel.log'));
        $this->assertSame('keep', file_get_contents($logs.'/notes.txt'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'log.cleared', 'instance_id' => $instance->id]);
    }

    /** Показываемый лог не удаляется, даже если в него давно не писали. */
    public function test_the_configured_log_is_emptied_even_when_stale(): void
    {
        $instance = $this->instanceWithLog("old\n");
        touch($this->base.'/storage/logs/laravel.log', time() - 3 * 86400);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->deleteJson(route('instances.log.destroy', $instance))
            ->assertOk();

        $this->assertFileExists($this->base.'/storage/logs/laravel.log');
    }

    public function test_clearing_without_logs_is_refused(): void
    {
        $instance = $this->instanceWithLog(null);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->deleteJson(route('instances.log.destroy', $instance))
            ->assertUnprocessable();
    }

    public function test_a_tester_without_access_cannot_clear_the_log(): void
    {
        $instance = $this->instanceWithLog("boom\n");
        $stranger = User::factory()->create(['role' => UserRole::Tester]);

        $this->actingAs($stranger)
            ->deleteJson(route('instances.log.destroy', $instance))
            ->assertForbidden();

        $this->assertSame("boom\n", file_get_contents($this->base.'/storage/logs/laravel.log'));
    }

    private function instanceWithLog(?string $contents): Instance
    {
        $this->base = sys_get_temp_dir().'/deployer-log-'.uniqid();
        mkdir($this->base.'/storage/logs', 0755, true);

        if ($contents !== null) {
            file_put_contents($this->base.'/storage/logs/laravel.log', $contents);
        }

        config(['deployer.allowed_path_prefixes' => [sys_get_temp_dir()]]);

        return Instance::factory()->create(['path' => $this->base]);
    }
}
