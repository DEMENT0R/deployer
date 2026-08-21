<?php

namespace Tests\Feature;

use App\Enums\DeployAction;
use App\Enums\UserRole;
use App\Jobs\DeployInstanceJob;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BackupActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tester_can_back_up_the_database_on_its_own(): void
    {
        Queue::fake();

        $tester = User::factory()->create(['role' => UserRole::Tester]);
        $instance = Instance::factory()->create([
            'backup_command' => 'mysqldump app > backup.sql',
        ]);
        $instance->users()->attach($tester);

        $this->actingAs($tester)
            ->post(route('instances.deploy', $instance), ['action' => DeployAction::Backup->value])
            ->assertSessionHasNoErrors();

        $deployment = $instance->deployments()->sole();

        $this->assertSame(DeployAction::Backup, $deployment->action);
        $this->assertNull($deployment->branch);

        Queue::assertPushed(DeployInstanceJob::class);
    }

    public function test_the_backup_action_runs_only_that_step(): void
    {
        $this->assertSame(['backup'], array_map(
            fn ($step) => $step->value,
            DeployAction::Backup->steps(),
        ));
    }

    /** Дамп имеет смысл только как снимок «до деплоя» — значит, раньше git и раньше миграций. */
    public function test_a_full_deploy_backs_up_before_touching_anything(): void
    {
        $steps = array_map(fn ($step) => $step->value, DeployAction::Full->steps());

        $this->assertSame('backup', $steps[0]);
        $this->assertLessThan(array_search('migrate', $steps, true), array_search('backup', $steps, true));
    }

    public function test_backing_up_without_a_command_is_refused(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create(['backup_command' => null]);

        $this->actingAs($admin)
            ->post(route('instances.deploy', $instance), ['action' => DeployAction::Backup->value])
            ->assertSessionHasErrors('deploy');

        Queue::assertNothingPushed();
        $this->assertSame(0, $instance->deployments()->count());
    }

    public function test_a_full_deploy_skips_an_empty_backup_command(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create(['backup_command' => null]);

        $this->actingAs($admin)
            ->post(route('instances.deploy', $instance), [
                'action' => DeployAction::Full->value,
                'branch' => 'main',
            ])
            ->assertSessionHasNoErrors();

        Queue::assertPushed(DeployInstanceJob::class);
    }

    public function test_the_instance_page_says_whether_there_is_a_backup_command(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $withCommand = Instance::factory()->create(['backup_command' => 'mysqldump app > backup.sql']);
        $without = Instance::factory()->create(['backup_command' => null]);

        $this->actingAs($admin)
            ->get(route('instances.show', $withCommand))
            ->assertInertia(fn ($page) => $page->where('instance.has_backup_command', true));

        $this->actingAs($admin)
            ->get(route('instances.show', $without))
            ->assertInertia(fn ($page) => $page->where('instance.has_backup_command', false));
    }

    /** Команда может содержать пароль от БД — наружу уходит только факт её наличия. */
    public function test_the_backup_command_itself_is_not_exposed_to_the_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create([
            'backup_command' => 'mysqldump -pS3cret app > backup.sql',
        ]);

        $this->actingAs($admin)
            ->get(route('instances.show', $instance))
            ->assertInertia(fn ($page) => $page->missing('instance.backup_command'));
    }

    /** Новому инстансу панель предлагает собственный скрипт бэкапа, а не пустое поле. */
    public function test_the_create_page_suggests_the_bundled_backup_script(): void
    {
        config(['deployer.backup_root' => '/var/backups/deployer', 'deployer.backup_keep' => 7]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->get(route('admin.instances.create'));
        $command = $response->viewData('page')['props']['default_backup_command'];

        $this->assertStringContainsString('scripts/backup-db.sh', $command);
        $this->assertStringContainsString('--root=/var/backups/deployer', $command);
        $this->assertStringContainsString('--keep=7', $command);
    }

    public function test_an_empty_backup_root_leaves_the_suggestion_out(): void
    {
        config(['deployer.backup_root' => '']);

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('admin.instances.create'))
            ->assertInertia(fn ($page) => $page->where('default_backup_command', ''));
    }

    /** У существующего инстанса пустая команда — осознанный отказ от шага, а не «не заполнено». */
    public function test_the_edit_page_does_not_suggest_a_backup_command(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create(['backup_command' => null]);

        $this->actingAs($admin)
            ->get(route('admin.instances.edit', $instance))
            ->assertInertia(fn ($page) => $page->missing('default_backup_command'));
    }
}
