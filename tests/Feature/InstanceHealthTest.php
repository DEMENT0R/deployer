<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InstanceHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_reachable_instance_reports_up(): void
    {
        Http::fake(['https://stage.example.com*' => Http::response('ok', 200)]);

        $instance = Instance::factory()->create(['url' => 'https://stage.example.com']);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->getJson(route('instances.health', $instance))
            ->assertOk()
            ->assertJson([
                'status' => 'up',
                'code' => 200,
                'url' => 'https://stage.example.com',
            ]);
    }

    public function test_error_response_reports_down_with_the_code(): void
    {
        Http::fake(['https://stage.example.com*' => Http::response('boom', 500)]);

        $instance = Instance::factory()->create(['url' => 'https://stage.example.com']);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->getJson(route('instances.health', $instance))
            ->assertOk()
            ->assertJson(['status' => 'down', 'code' => 500]);
    }

    public function test_connection_failure_reports_unreachable(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $instance = Instance::factory()->create(['url' => 'https://stage.example.com']);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->getJson(route('instances.health', $instance))
            ->assertOk()
            ->assertJson(['status' => 'unreachable', 'code' => null]);
    }

    public function test_instance_without_url_is_not_pinged(): void
    {
        Http::fake();

        $instance = Instance::factory()->create(['url' => null]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->getJson(route('instances.health', $instance))
            ->assertOk()
            ->assertJson(['status' => 'not_configured']);

        Http::assertNothingSent();
    }

    public function test_non_http_url_is_not_fetched(): void
    {
        Http::fake();

        // Схему стережёт валидация формы, но в БД можно попасть и мимо неё.
        $instance = Instance::factory()->create();
        $instance->forceFill(['url' => 'file:///etc/passwd'])->save();

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->getJson(route('instances.health', $instance))
            ->assertOk()
            ->assertJson(['status' => 'not_configured']);

        Http::assertNothingSent();
    }

    public function test_tester_cannot_ping_an_instance_they_do_not_own(): void
    {
        Http::fake();

        $instance = Instance::factory()->create(['url' => 'https://stage.example.com']);
        $tester = User::factory()->create(['role' => UserRole::Tester]);

        $this->actingAs($tester)
            ->getJson(route('instances.health', $instance))
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_url_must_be_http_or_https_when_saved(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->post(route('admin.instances.store'), $this->instancePayload(['url' => 'javascript:alert(1)']))
            ->assertSessionHasErrors('url');

        $this->actingAs($admin)
            ->post(route('admin.instances.store'), $this->instancePayload(['url' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull(Instance::query()->latest('id')->first()->url);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function instancePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Stage',
            'path' => '/var/www/stage',
            'platform' => 'linux',
            'git_remote' => 'origin',
            'default_branch' => 'main',
            'migrate_command' => 'php artisan migrate --force',
            'frontend_command' => 'npm ci && npm run build',
            'is_active' => true,
        ], $overrides);
    }
}
