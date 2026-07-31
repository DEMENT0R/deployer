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

    public function test_initiator_gets_a_mail_when_enabled(): void
    {
        // Панельный канал глушим: этот тест именно про письмо.
        config(['deployer.notify_on_finish' => true, 'deployer.notify_in_panel' => false]);
        Notification::fake();

        $user = User::factory()->create(['role' => UserRole::Admin]);
        $deployment = $this->pendingDeployment($user);

        $this->runJob($deployment);

        Notification::assertSentTo(
            $user,
            DeploymentFinished::class,
            fn ($notification, array $channels) => $channels === ['mail'],
        );
    }

    public function test_no_mail_when_only_the_panel_channel_is_on(): void
    {
        config(['deployer.notify_on_finish' => false, 'deployer.notify_in_panel' => true]);
        Notification::fake();

        $user = User::factory()->create(['role' => UserRole::Admin]);
        $deployment = $this->pendingDeployment($user);

        $this->runJob($deployment);

        Notification::assertSentTo(
            $user,
            DeploymentFinished::class,
            fn ($notification, array $channels) => $channels === ['database'],
        );
    }

    public function test_nothing_is_sent_when_both_channels_are_off(): void
    {
        config(['deployer.notify_on_finish' => false, 'deployer.notify_in_panel' => false]);
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
