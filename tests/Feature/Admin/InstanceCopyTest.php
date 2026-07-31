<?php

namespace Tests\Feature\Admin;

use App\Enums\DeployAction;
use App\Enums\DeployStatus;
use App\Enums\UserRole;
use App\Jobs\DeployInstanceJob;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class InstanceCopyTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_exposes_the_source_for_the_copy_option(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create(['path' => '/var/www/app']);

        $this->actingAs($admin)
            ->get(route('admin.instances.duplicate', $instance))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Instances/Form')
                ->where('prefill.source_instance_id', $instance->id)
                ->where('prefill.source_path', '/var/www/app')
            );
    }

    public function test_creating_with_copy_files_queues_a_copy_deployment(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $source = Instance::factory()->create(['path' => '/var/www/app']);

        $this->actingAs($admin)
            ->post(route('admin.instances.store'), $this->payload([
                'copy_files' => true,
                'source_instance_id' => $source->id,
            ]))
            ->assertSessionHasNoErrors();

        $instance = Instance::where('path', '/var/www/new')->sole();
        $deployment = $instance->deployments()->sole();

        $this->assertSame(DeployAction::Copy, $deployment->action);
        $this->assertSame(DeployStatus::Pending, $deployment->status);
        $this->assertSame($source->id, $deployment->source_instance_id);
        $this->assertSame($admin->id, $deployment->user_id);

        Queue::assertPushed(DeployInstanceJob::class);
    }

    public function test_creating_without_copy_files_touches_no_deployment(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->post(route('admin.instances.store'), $this->payload())
            ->assertRedirect(route('admin.instances.index'));

        $this->assertSame(0, Instance::where('path', '/var/www/new')->sole()->deployments()->count());
        Queue::assertNothingPushed();
    }

    public function test_copy_is_rejected_for_a_windows_instance(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $source = Instance::factory()->create(['path' => '/var/www/app']);

        $this->actingAs($admin)
            ->post(route('admin.instances.store'), $this->payload([
                'platform' => 'windows',
                'copy_files' => true,
                'source_instance_id' => $source->id,
            ]))
            ->assertSessionHasErrors('copy_files');

        $this->assertSame(0, Instance::where('path', '/var/www/new')->count());
        Queue::assertNothingPushed();
    }

    public function test_copy_requires_a_source_instance(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->post(route('admin.instances.store'), $this->payload(['copy_files' => true]))
            ->assertSessionHasErrors('source_instance_id');

        Queue::assertNothingPushed();
    }

    public function test_updating_an_instance_never_starts_a_copy(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $source = Instance::factory()->create(['path' => '/var/www/app']);
        $instance = Instance::factory()->create(['path' => '/var/www/new']);

        $this->actingAs($admin)
            ->put(route('admin.instances.update', $instance), $this->payload([
                'copy_files' => true,
                'source_instance_id' => $source->id,
            ]))
            ->assertSessionHasNoErrors();

        Queue::assertNothingPushed();
        $this->assertSame(0, $instance->deployments()->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'App copy',
            'path' => '/var/www/new',
            'platform' => 'linux',
            'git_remote' => 'origin',
            'default_branch' => 'main',
            'migrate_command' => 'php artisan migrate --force',
            'frontend_command' => 'npm ci && npm run build',
            'is_active' => true,
        ], $overrides);
    }
}
