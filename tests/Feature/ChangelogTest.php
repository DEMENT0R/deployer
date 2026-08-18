<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Changelog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ChangelogTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_lists_parsed_entries(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($user)
            ->get(route('changelog.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Changelog/Index')
                ->has('entries.0.date')
                ->has('entries.0.html')
                ->where('entries.0.date', Changelog::latestDate())
            );
    }

    public function test_visiting_marks_changelog_as_seen(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $this->assertNull($user->changelog_seen_at);

        $this->actingAs($user)->get(route('changelog.index'))->assertOk();

        $this->assertNotNull($user->fresh()->changelog_seen_at);
    }

    public function test_unseen_flag_is_shared_until_the_page_is_visited(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($user)
            ->get(route('instances.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('hasUnseenChangelog', true));

        $this->actingAs($user)->get(route('changelog.index'))->assertOk();

        $this->actingAs($user)
            ->get(route('instances.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('hasUnseenChangelog', false));
    }

    public function test_guest_cannot_access_changelog(): void
    {
        $this->get(route('changelog.index'))->assertRedirect(route('login'));
    }
}
