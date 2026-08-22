<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_an_instance_is_recorded_with_the_changed_fields(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create(['name' => 'Billing stand']);

        $this->actingAs($admin)
            ->put(route('admin.instances.update', $instance), $this->instancePayload($instance, [
                'default_branch' => 'develop',
            ]))
            ->assertSessionHasNoErrors();

        $entry = AuditLog::query()->sole();

        $this->assertSame('instance.updated', $entry->action);
        $this->assertSame('Billing stand', $entry->subject);
        $this->assertSame($admin->id, $entry->user_id);
        $this->assertSame($instance->id, $entry->instance_id);
        $this->assertStringContainsString('default_branch', (string) $entry->summary);
    }

    public function test_changing_the_testers_of_an_instance_is_recorded(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $tester = User::factory()->create(['role' => UserRole::Tester]);
        $instance = Instance::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.instances.update', $instance), $this->instancePayload($instance, [
                'tester_ids' => [$tester->id],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertStringContainsString('testers', (string) AuditLog::query()->sole()->summary);
    }

    /** Запись должна пережить сам инстанс: иначе «кто его удалил» узнать неоткуда. */
    public function test_deleting_an_instance_leaves_its_name_in_the_journal(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create(['name' => 'Old stand']);

        $this->actingAs($admin)
            ->delete(route('admin.instances.destroy', $instance))
            ->assertSessionHasNoErrors();

        $entry = AuditLog::query()->sole();

        $this->assertSame('instance.deleted', $entry->action);
        $this->assertSame('Old stand', $entry->subject);
        $this->assertNull($entry->instance_id);
    }

    public function test_user_changes_are_recorded_without_the_password(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $user = User::factory()->create(['role' => UserRole::Tester, 'email' => 'qa@example.com']);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'role' => UserRole::Tester->value,
                'password' => 'new-password-123',
            ])
            ->assertSessionHasNoErrors();

        $entry = AuditLog::query()->sole();

        $this->assertSame('user.updated', $entry->action);
        $this->assertSame('qa@example.com', $entry->subject);
        $this->assertStringContainsString('password', (string) $entry->summary);
        $this->assertStringNotContainsString('new-password-123', (string) $entry->summary);
    }

    public function test_the_journal_is_admin_only(): void
    {
        $tester = User::factory()->create(['role' => UserRole::Tester]);

        $this->actingAs($tester)
            ->get(route('admin.audit.index'))
            ->assertForbidden();
    }

    public function test_the_journal_page_lists_entries_newest_first(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        AuditLog::create(['action' => 'env.updated', 'subject' => 'first'])
            ->forceFill(['created_at' => now()->subHour()])->save();
        AuditLog::create(['action' => 'screen.stopped', 'subject' => 'second']);

        $this->actingAs($admin)
            ->get(route('admin.audit.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('entries.data.0.subject', 'second')
                ->where('entries.data.1.subject', 'first')
            );
    }

    public function test_the_journal_can_be_filtered_by_action(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        AuditLog::create(['action' => 'env.updated', 'subject' => 'env one']);
        AuditLog::create(['action' => 'screen.stopped', 'subject' => 'session one']);

        $this->actingAs($admin)
            ->get(route('admin.audit.index', ['action' => 'env.updated']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->count('entries.data', 1)
                ->where('entries.data.0.subject', 'env one')
            );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function instancePayload(Instance $instance, array $overrides = []): array
    {
        return array_merge([
            'name' => $instance->name,
            'path' => $instance->path,
            'platform' => $instance->platform->value,
            'git_remote' => $instance->git_remote,
            'default_branch' => $instance->default_branch,
            'migrate_command' => $instance->migrate_command,
            'frontend_command' => $instance->frontend_command,
            'is_active' => $instance->is_active,
            'tester_ids' => [],
        ], $overrides);
    }
}
