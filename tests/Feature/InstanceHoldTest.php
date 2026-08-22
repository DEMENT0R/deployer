<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InstanceHoldTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tester_can_take_the_stand(): void
    {
        [$instance, $tester] = $this->instanceWithTester();

        $this->actingAs($tester)
            ->post(route('instances.hold.store', $instance), ['hours' => 8, 'note' => 'Release check'])
            ->assertSessionHasNoErrors();

        $instance->refresh();

        $this->assertTrue($instance->isHeld());
        $this->assertSame($tester->id, $instance->held_by_user_id);
        $this->assertSame('Release check', $instance->hold_note);
        $this->assertEqualsWithDelta(8 * 3600, now()->diffInSeconds($instance->held_until), 60);
    }

    public function test_taking_a_stand_someone_else_holds_is_refused(): void
    {
        [$instance, $tester] = $this->instanceWithTester();
        $other = User::factory()->create(['role' => UserRole::Tester]);
        $instance->users()->attach($other);

        $instance->update(['held_by_user_id' => $other->id, 'held_until' => now()->addHours(2)]);

        $this->actingAs($tester)
            ->post(route('instances.hold.store', $instance), ['hours' => 4])
            ->assertSessionHasErrors('hold');

        $this->assertSame($other->id, $instance->refresh()->held_by_user_id);
    }

    /** Просроченная бронь ничего не держит: стенд свободен, и занять его может любой. */
    public function test_an_expired_hold_does_not_block_anyone(): void
    {
        [$instance, $tester] = $this->instanceWithTester();
        $other = User::factory()->create(['role' => UserRole::Tester]);

        $instance->update(['held_by_user_id' => $other->id, 'held_until' => now()->subMinute()]);

        $this->assertFalse($instance->isHeld());

        $this->actingAs($tester)
            ->post(route('instances.hold.store', $instance), ['hours' => 1])
            ->assertSessionHasNoErrors();

        $this->assertSame($tester->id, $instance->refresh()->held_by_user_id);
    }

    public function test_the_holder_can_release_the_stand(): void
    {
        [$instance, $tester] = $this->instanceWithTester();
        $instance->update(['held_by_user_id' => $tester->id, 'held_until' => now()->addHours(2)]);

        $this->actingAs($tester)
            ->delete(route('instances.hold.destroy', $instance))
            ->assertSessionHasNoErrors();

        $this->assertNull($instance->refresh()->held_by_user_id);
    }

    /** Забытая чужая бронь не должна держать стенд до истечения срока. */
    public function test_an_admin_can_release_someone_elses_hold(): void
    {
        [$instance, $tester] = $this->instanceWithTester();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance->update(['held_by_user_id' => $tester->id, 'held_until' => now()->addHours(2)]);

        $this->actingAs($admin)
            ->delete(route('instances.hold.destroy', $instance))
            ->assertSessionHasNoErrors();

        $this->assertNull($instance->refresh()->held_by_user_id);
    }

    public function test_a_stranger_can_neither_take_nor_release_the_stand(): void
    {
        [$instance, $tester] = $this->instanceWithTester();
        $stranger = User::factory()->create(['role' => UserRole::Tester]);
        $instance->update(['held_by_user_id' => $tester->id, 'held_until' => now()->addHours(2)]);

        $this->actingAs($stranger)
            ->post(route('instances.hold.store', $instance), ['hours' => 1])
            ->assertForbidden();

        $this->actingAs($stranger)
            ->delete(route('instances.hold.destroy', $instance))
            ->assertForbidden();
    }

    /** Занятость предупреждает, но не запрещает: перекатить срочно иногда всё равно нужно. */
    public function test_a_deploy_to_a_held_stand_still_goes_through(): void
    {
        Queue::fake();

        [$instance, $tester] = $this->instanceWithTester();
        $other = User::factory()->create(['role' => UserRole::Tester]);
        $instance->update(['held_by_user_id' => $other->id, 'held_until' => now()->addHours(2)]);

        $this->actingAs($tester)
            ->post(route('instances.deploy', $instance), ['action' => 'branch', 'branch' => 'main'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $instance->deployments()->count());
    }

    public function test_the_hold_is_visible_on_the_instance_page(): void
    {
        [$instance, $tester] = $this->instanceWithTester();
        $instance->update([
            'held_by_user_id' => $tester->id,
            'held_until' => now()->addHours(2),
            'hold_note' => 'Regression',
        ]);

        $this->actingAs($tester)
            ->get(route('instances.show', $instance))
            ->assertInertia(fn ($page) => $page
                ->where('instance.hold.user_id', $tester->id)
                ->where('instance.hold.note', 'Regression')
            );
    }

    public function test_taking_and_releasing_are_written_to_the_journal(): void
    {
        [$instance, $tester] = $this->instanceWithTester();

        $this->actingAs($tester)->post(route('instances.hold.store', $instance), ['hours' => 1]);
        $this->actingAs($tester)->delete(route('instances.hold.destroy', $instance));

        $this->assertSame(
            ['instance.held', 'instance.released'],
            AuditLog::query()->orderBy('id')->pluck('action')->all(),
        );
    }

    /**
     * @return array{0: Instance, 1: User}
     */
    private function instanceWithTester(): array
    {
        $instance = Instance::factory()->create();
        $tester = User::factory()->create(['role' => UserRole::Tester]);
        $instance->users()->attach($tester);

        return [$instance, $tester];
    }
}
