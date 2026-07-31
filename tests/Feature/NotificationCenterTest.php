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
use Inertia\Testing\AssertableInertia;
use Mockery;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_notification_lands_in_the_database(): void
    {
        config(['deployer.notify_in_panel' => true, 'deployer.notify_on_finish' => false]);

        $user = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create(['name' => 'Staging']);
        $deployment = $this->runDeploy($instance, $user);

        $this->assertSame(1, $user->notifications()->count());

        $data = $user->notifications()->sole()->data;
        $this->assertSame($instance->id, $data['instance_id']);
        $this->assertSame('Staging', $data['instance_name']);
        $this->assertSame($deployment->id, $data['deployment_id']);
        $this->assertSame('success', $data['status']);
    }

    public function test_channels_follow_the_settings(): void
    {
        Notification::fake();

        config(['deployer.notify_in_panel' => true, 'deployer.notify_on_finish' => true]);
        $this->assertSame(['database', 'mail'], DeploymentFinished::enabledChannels());

        config(['deployer.notify_in_panel' => false, 'deployer.notify_on_finish' => true]);
        $this->assertSame(['mail'], DeploymentFinished::enabledChannels());

        config(['deployer.notify_in_panel' => false, 'deployer.notify_on_finish' => false]);
        $this->assertSame([], DeploymentFinished::enabledChannels());
    }

    public function test_nothing_is_sent_when_both_channels_are_off(): void
    {
        config(['deployer.notify_in_panel' => false, 'deployer.notify_on_finish' => false]);
        Notification::fake();

        $user = User::factory()->create(['role' => UserRole::Admin]);
        $this->runDeploy(Instance::factory()->create(), $user);

        Notification::assertNothingSent();
    }

    public function test_unread_count_is_shared_with_the_frontend(): void
    {
        config(['deployer.notify_in_panel' => true]);

        $user = User::factory()->create(['role' => UserRole::Admin]);
        $this->runDeploy(Instance::factory()->create(), $user);

        $this->actingAs($user)
            ->get(route('instances.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('unreadNotifications', 1));
    }

    public function test_list_shows_only_own_notifications(): void
    {
        config(['deployer.notify_in_panel' => true]);

        $mine = User::factory()->create(['role' => UserRole::Admin]);
        $other = User::factory()->create(['role' => UserRole::Admin]);

        $this->runDeploy(Instance::factory()->create(['name' => 'Mine']), $mine);
        $this->runDeploy(Instance::factory()->create(['name' => 'Theirs']), $other);

        $response = $this->actingAs($mine)
            ->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonCount(1, 'notifications')
            ->assertJsonPath('unread_count', 1);

        $this->assertSame('Mine', $response->json('notifications.0.data.instance_name'));
        $response->assertDontSee('Theirs');
    }

    public function test_marking_one_as_read_drops_the_count(): void
    {
        config(['deployer.notify_in_panel' => true]);

        $user = User::factory()->create(['role' => UserRole::Admin]);
        $this->runDeploy(Instance::factory()->create(), $user);
        $id = $user->notifications()->sole()->id;

        $this->actingAs($user)
            ->postJson(route('notifications.read', $id))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertNotNull($user->notifications()->sole()->read_at);
    }

    public function test_cannot_mark_someone_elses_notification_as_read(): void
    {
        config(['deployer.notify_in_panel' => true]);

        $mine = User::factory()->create(['role' => UserRole::Admin]);
        $other = User::factory()->create(['role' => UserRole::Admin]);
        $this->runDeploy(Instance::factory()->create(), $other);
        $foreign = $other->notifications()->sole()->id;

        $this->actingAs($mine)
            ->postJson(route('notifications.read', $foreign))
            ->assertNotFound();

        $this->assertNull($other->notifications()->sole()->read_at);
    }

    public function test_mark_all_read(): void
    {
        config(['deployer.notify_in_panel' => true]);

        $user = User::factory()->create(['role' => UserRole::Admin]);
        $this->runDeploy(Instance::factory()->create(), $user);
        $this->runDeploy(Instance::factory()->create(), $user);

        $this->actingAs($user)
            ->postJson(route('notifications.read-all'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_guest_cannot_read_notifications(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    }

    /**
     * Прогоняет джобу с подменённым деплоем: интересует только уведомление после завершения.
     */
    private function runDeploy(Instance $instance, User $user): Deployment
    {
        $deployment = Deployment::create([
            'instance_id' => $instance->id,
            'user_id' => $user->id,
            'branch' => 'main',
            'action' => DeployAction::Full,
            'status' => DeployStatus::Pending,
        ]);

        $deployer = Mockery::mock(InstanceDeployer::class);
        $deployer->shouldReceive('deploy')->once()->andReturnUsing(
            fn (Deployment $d) => $d->update(['status' => DeployStatus::Success]),
        );

        (new DeployInstanceJob($deployment->id))->handle($deployer);

        return $deployment;
    }
}
