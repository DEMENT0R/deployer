<?php

namespace Tests\Feature;

use App\Enums\DeployAction;
use App\Enums\DeployStatus;
use App\Exceptions\PathValidationException;
use App\Models\Deployment;
use App\Models\Instance;
use App\Models\User;
use App\Services\Deploy\InstanceDeployer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Путь резолвится внутри try: раньше исключение отсюда пролетало мимо обработчика, деплой
 * навсегда оставался в running с пустым логом, а причина оседала только в laravel.log.
 */
class DeployPathFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_bad_path_fails_the_copy_deployment_with_a_visible_reason(): void
    {
        $deployment = $this->deploymentFor(DeployAction::Copy, '/nowhere/near/allowed');

        $this->runDeployer($deployment);

        $deployment->refresh();

        $this->assertSame(DeployStatus::Failed, $deployment->status);
        $this->assertStringContainsString('not within allowed prefixes', (string) $deployment->output);
        $this->assertNotNull($deployment->finished_at);
    }

    public function test_a_bad_path_fails_a_regular_deployment_too(): void
    {
        $deployment = $this->deploymentFor(DeployAction::Migrate, '/var/www/gone');

        $this->runDeployer($deployment);

        $deployment->refresh();

        $this->assertSame(DeployStatus::Failed, $deployment->status);
        $this->assertStringContainsString('does not exist', (string) $deployment->output);
    }

    public function test_the_steps_of_a_failed_deployment_are_still_listed(): void
    {
        $deployment = $this->deploymentFor(DeployAction::Copy, '/nowhere/near/allowed');

        $this->runDeployer($deployment);

        // Без шагов страница деплоя показывала бы пустой прогресс — падение должно быть видно.
        $this->assertSame(['copy', 'composer', 'cache', 'frontend'], array_keys($deployment->refresh()->steps ?? []));
    }

    private function runDeployer(Deployment $deployment): void
    {
        try {
            app(InstanceDeployer::class)->deploy($deployment);
            $this->fail('Expected the deployer to rethrow the path failure.');
        } catch (PathValidationException) {
            // Джоба ловит это и репортит; нас интересует состояние записи деплоя.
        }
    }

    private function deploymentFor(DeployAction $action, string $path): Deployment
    {
        config(['deployer.allowed_path_prefixes' => ['/var/www']]);

        $instance = Instance::factory()->create(['path' => $path, 'allowed_path_prefix' => null]);

        return Deployment::create([
            'instance_id' => $instance->id,
            'source_instance_id' => Instance::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
            'action' => $action,
            'status' => DeployStatus::Running,
        ]);
    }
}
