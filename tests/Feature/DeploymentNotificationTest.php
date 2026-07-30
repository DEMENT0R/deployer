<?php

namespace Tests\Feature;

use App\Enums\DeployAction;
use App\Enums\DeployStatus;
use App\Enums\UserRole;
use App\Jobs\DeployInstanceJob;
use App\Models\Deployment;
use App\Models\Instance;
use App\Models\User;
use App\Notifications\DeploymentFinished;
use App\Services\Deploy\InstanceDeployer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

class DeploymentNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_initiator_is_notified_when_enabled(): void
    {
        config(['deployer.notify_on_finish' => true]);
        Notification::fake();

        $user = User::factory()->create(['role' => UserRole::Admin]);
        $deployment = $this->pendingDeployment($user);

        $this->runJob($deployment);

        Notification::assertSentTo($user, DeploymentFinished::class);
    }

    public function test_no_notification_when_disabled(): void
    {
        config(['deployer.notify_on_finish' => false]);
        Notification::fake();

        $user = User::factory()->create(['role' => UserRole::Admin]);
        $deployment = $this->pendingDeployment($user);

        $this->runJob($deployment);

        Notification::assertNothingSent();
    }

    private function pendingDeployment(User $user): Deployment
    {
        $instance = Instance::factory()->create();

        return Deployment::create([
            'instance_id' => $instance->id,
            'user_id' => $user->id,
            'branch' => 'main',
            'action' => DeployAction::Full,
            'status' => DeployStatus::Pending,
        ]);
    }

    private function runJob(Deployment $deployment): void
    {
        // Сам деплой подменяем: проверяем только уведомление после завершения джобы.
        $deployer = Mockery::mock(InstanceDeployer::class);
        $deployer->shouldReceive('deploy')->once()->andReturnUsing(
            fn (Deployment $d) => $d->update(['status' => DeployStatus::Success]),
        );

        (new DeployInstanceJob($deployment->id))->handle($deployer);
    }
}
