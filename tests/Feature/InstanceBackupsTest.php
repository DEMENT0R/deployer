<?php

namespace Tests\Feature;

use App\Enums\DeployAction;
use App\Enums\UserRole;
use App\Jobs\DeployInstanceJob;
use App\Models\Instance;
use App\Models\User;
use App\Services\InstanceBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InstanceBackupsTest extends TestCase
{
    use RefreshDatabase;

    private ?string $root = null;

    protected function tearDown(): void
    {
        if ($this->root !== null && is_dir($this->root)) {
            foreach (glob($this->root.'/*/*') ?: [] as $file) {
                @unlink($file);
            }

            foreach (glob($this->root.'/*') ?: [] as $directory) {
                @rmdir($directory);
            }

            @rmdir($this->root);
        }

        parent::tearDown();
    }

    public function test_the_dump_directory_comes_from_the_instances_own_command(): void
    {
        $instance = Instance::factory()->create([
            'path' => '/var/www/my-stand',
            'backup_command' => 'bash /var/www/deployer/scripts/backup-db.sh --root=/var/backups/deployer --keep=10',
        ]);

        $this->assertSame(
            '/var/backups/deployer/my-stand',
            app(InstanceBackupService::class)->directory($instance),
        );
    }

    public function test_an_explicit_slug_wins_over_the_directory_name(): void
    {
        $instance = Instance::factory()->create([
            'path' => '/var/www/my-stand',
            'backup_command' => 'bash /var/www/deployer/scripts/backup-db.sh --root=/var/backups/deployer --keep=10 billing',
        ]);

        $this->assertSame(
            '/var/backups/deployer/billing',
            app(InstanceBackupService::class)->directory($instance),
        );
    }

    /** Куда пишет чужая команда — панель не знает и знать не может. */
    public function test_a_foreign_backup_command_has_no_known_directory(): void
    {
        $instance = Instance::factory()->create([
            'backup_command' => 'mysqldump app | gzip > /home/deploy/app.sql.gz',
        ]);

        $this->assertNull(app(InstanceBackupService::class)->directory($instance));
    }

    public function test_admin_gets_the_dumps_newest_first(): void
    {
        $instance = $this->instanceWithDumps(['app_2026-08-20_0300.sql.gz', 'app_2026-08-21_0300.sql.gz']);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)
            ->getJson(route('instances.backups.index', $instance))
            ->assertOk();

        $this->assertSame(
            ['app_2026-08-21_0300.sql.gz', 'app_2026-08-20_0300.sql.gz'],
            array_column($response->json('dumps'), 'name'),
        );
    }

    public function test_a_tester_cannot_list_the_dumps(): void
    {
        $instance = $this->instanceWithDumps(['app_2026-08-21_0300.sql.gz']);
        $tester = User::factory()->create(['role' => UserRole::Tester]);
        $instance->users()->attach($tester);

        $this->actingAs($tester)
            ->getJson(route('instances.backups.index', $instance))
            ->assertForbidden();
    }

    public function test_only_files_from_the_directory_resolve_to_a_path(): void
    {
        $instance = $this->instanceWithDumps(['app_2026-08-21_0300.sql.gz']);
        $service = app(InstanceBackupService::class);

        $this->assertNotNull($service->resolve($instance, 'app_2026-08-21_0300.sql.gz'));
        $this->assertNull($service->resolve($instance, 'missing.sql.gz'));
        $this->assertNull($service->resolve($instance, '../../etc/passwd'));
    }

    public function test_admin_can_queue_a_restore_from_a_dump(): void
    {
        Queue::fake();

        $instance = $this->instanceWithDumps(['app_2026-08-21_0300.sql.gz']);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->post(route('instances.restore', $instance), ['file' => 'app_2026-08-21_0300.sql.gz'])
            ->assertSessionHasNoErrors();

        $deployment = $instance->deployments()->sole();

        $this->assertSame(DeployAction::Restore, $deployment->action);
        $this->assertSame('app_2026-08-21_0300.sql.gz', $deployment->branch);

        Queue::assertPushed(DeployInstanceJob::class);
    }

    public function test_a_tester_cannot_restore(): void
    {
        Queue::fake();

        $instance = $this->instanceWithDumps(['app_2026-08-21_0300.sql.gz']);
        $tester = User::factory()->create(['role' => UserRole::Tester]);
        $instance->users()->attach($tester);

        $this->actingAs($tester)
            ->post(route('instances.restore', $instance), ['file' => 'app_2026-08-21_0300.sql.gz'])
            ->assertForbidden();

        Queue::assertNothingPushed();
        $this->assertSame(0, $instance->deployments()->count());
    }

    /** Имя приходит из браузера: принимаем только то, что и правда лежит в каталоге. */
    public function test_restoring_an_unknown_dump_is_refused(): void
    {
        Queue::fake();

        $instance = $this->instanceWithDumps(['app_2026-08-21_0300.sql.gz']);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->post(route('instances.restore', $instance), ['file' => '../../etc/passwd'])
            ->assertSessionHasErrors('deploy');

        Queue::assertNothingPushed();
        $this->assertSame(0, $instance->deployments()->count());
    }

    public function test_the_restore_action_runs_only_that_step(): void
    {
        $this->assertSame(['restore'], array_map(
            fn ($step) => $step->value,
            DeployAction::Restore->steps(),
        ));
    }

    /** Тестер не должен дотянуться до наката базы и через общий эндпоинт деплоя. */
    public function test_restore_is_not_triggerable_through_the_deploy_endpoint(): void
    {
        Queue::fake();

        $instance = $this->instanceWithDumps(['app_2026-08-21_0300.sql.gz']);
        $tester = User::factory()->create(['role' => UserRole::Tester]);
        $instance->users()->attach($tester);

        $this->actingAs($tester)
            ->post(route('instances.deploy', $instance), ['action' => DeployAction::Restore->value])
            ->assertSessionHasErrors('action');

        Queue::assertNothingPushed();
    }

    /**
     * @param  list<string>  $names
     */
    private function instanceWithDumps(array $names): Instance
    {
        $this->root = sys_get_temp_dir().'/deployer-dumps-'.uniqid();
        mkdir($this->root.'/my-stand', 0755, true);

        $modified = strtotime('2026-08-01 00:00:00');

        foreach ($names as $name) {
            file_put_contents($this->root.'/my-stand/'.$name, 'dump');
            touch($this->root.'/my-stand/'.$name, $modified += 3600);
        }

        return Instance::factory()->create([
            'path' => '/var/www/my-stand',
            'backup_command' => "bash /var/www/deployer/scripts/backup-db.sh --root={$this->root} --keep=10",
        ]);
    }
}
