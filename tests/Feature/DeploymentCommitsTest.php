<?php

namespace Tests\Feature;

use App\Enums\DeployAction;
use App\Enums\DeployStatus;
use App\Enums\UserRole;
use App\Models\Deployment;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class DeploymentCommitsTest extends TestCase
{
    use RefreshDatabase;

    private ?string $repo = null;

    protected function tearDown(): void
    {
        if ($this->repo !== null && is_dir($this->repo)) {
            $this->deleteTree($this->repo);
        }

        parent::tearDown();
    }

    public function test_reports_when_commits_were_not_recorded(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create();
        $deployment = $this->deployment($instance, $admin, null, null);

        $this->actingAs($admin)
            ->getJson(route('instances.deployments.commits', [$instance, $deployment]))
            ->assertOk()
            ->assertJsonPath('status', 'unknown')
            ->assertJsonPath('commits', []);
    }

    public function test_reports_when_the_tree_did_not_move(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create();
        $deployment = $this->deployment($instance, $admin, 'abc1234', 'abc1234');

        $this->actingAs($admin)
            ->getJson(route('instances.deployments.commits', [$instance, $deployment]))
            ->assertOk()
            ->assertJsonPath('status', 'unchanged');
    }

    public function test_missing_working_copy_is_reported_not_thrown(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create(['path' => '/var/www/does-not-exist']);
        $deployment = $this->deployment($instance, $admin, 'abc1234', 'def5678');

        $this->actingAs($admin)
            ->getJson(route('instances.deployments.commits', [$instance, $deployment]))
            ->assertOk()
            ->assertJsonPath('status', 'path_error');
    }

    public function test_lists_the_commits_a_deploy_brought_in(): void
    {
        [$instance, $first, $second, $third] = $this->instanceWithRepo();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        // Деплой увёз дерево с первого коммита на третий: внутри второй и третий.
        $deployment = $this->deployment($instance, $admin, $first, $third);

        $response = $this->actingAs($admin)
            ->getJson(route('instances.deployments.commits', [$instance, $deployment]))
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('direction', 'deployed')
            ->assertJsonPath('truncated', false)
            ->assertJsonCount(2, 'commits');

        $subjects = array_column($response->json('commits'), 'subject');
        // Новейший коммит идёт первым — как в git log.
        $this->assertSame(['Third', 'Second'], $subjects);
        $this->assertNotContains('First', $subjects);
    }

    public function test_rollback_lists_the_commits_it_removed(): void
    {
        [$instance, $first, , $third] = $this->instanceWithRepo();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        // Откат ведёт дерево назад: с третьего на первый.
        $deployment = $this->deployment($instance, $admin, $third, $first, DeployAction::Rollback);

        $response = $this->actingAs($admin)
            ->getJson(route('instances.deployments.commits', [$instance, $deployment]))
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('direction', 'reverted')
            ->assertJsonCount(2, 'commits');

        $this->assertSame(['Third', 'Second'], array_column($response->json('commits'), 'subject'));
    }

    public function test_tester_without_access_is_denied(): void
    {
        $tester = User::factory()->create(['role' => UserRole::Tester]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create();
        $deployment = $this->deployment($instance, $admin, 'abc1234', 'def5678');

        $this->actingAs($tester)
            ->getJson(route('instances.deployments.commits', [$instance, $deployment]))
            ->assertForbidden();
    }

    public function test_deployment_of_another_instance_is_not_found(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create();
        $other = Instance::factory()->create();
        $foreign = $this->deployment($other, $admin, 'abc1234', 'def5678');

        // scopeBindings: чужой деплой не должен находиться под этим инстансом.
        $this->actingAs($admin)
            ->getJson(route('instances.deployments.commits', [$instance, $foreign]))
            ->assertNotFound();
    }

    /**
     * Настоящий репозиторий с тремя коммитами: проверяем реальный `git log`, а не мок.
     *
     * @return array{0: Instance, 1: string, 2: string, 3: string}
     */
    private function instanceWithRepo(): array
    {
        $this->repo = sys_get_temp_dir().'/deployer-commits-'.uniqid();
        mkdir($this->repo, 0755, true);

        $this->git(['init', '--initial-branch=main']);

        $shas = [];

        foreach (['First', 'Second', 'Third'] as $subject) {
            file_put_contents($this->repo.'/file.txt', $subject);
            $this->git(['add', 'file.txt']);
            // Автора задаём флагами: тесты не должны зависеть от глобального git-конфига.
            $this->git([
                '-c', 'user.email=test@example.com',
                '-c', 'user.name=Test',
                'commit', '-m', $subject,
            ]);
            $shas[] = trim($this->git(['rev-parse', 'HEAD']));
        }

        config(['deployer.allowed_path_prefixes' => [$this->repo]]);

        $instance = Instance::factory()->create([
            'path' => $this->repo,
            'allowed_path_prefix' => $this->repo,
        ]);

        return [$instance, $shas[0], $shas[1], $shas[2]];
    }

    /**
     * @param  list<string>  $args
     */
    private function git(array $args): string
    {
        $process = new Process(['git', ...$args], $this->repo);
        $process->mustRun();

        return $process->getOutput();
    }

    private function deployment(
        Instance $instance,
        User $user,
        ?string $before,
        ?string $after,
        DeployAction $action = DeployAction::Full,
    ): Deployment {
        return Deployment::create([
            'instance_id' => $instance->id,
            'user_id' => $user->id,
            'branch' => 'main',
            'commit_before' => $before,
            'commit_after' => $after,
            'action' => $action,
            'status' => DeployStatus::Success,
        ]);
    }

    private function deleteTree(string $path): void
    {
        // .git содержит read-only файлы — на Windows их надо разрешить к удалению.
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $path.DIRECTORY_SEPARATOR.$entry;

            if (is_dir($full)) {
                $this->deleteTree($full);
            } else {
                @chmod($full, 0666);
                @unlink($full);
            }
        }

        @rmdir($path);
    }
}
