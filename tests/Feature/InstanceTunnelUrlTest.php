<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class InstanceTunnelUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_instance_page_offers_a_tunnel_link_built_from_the_serve_port(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create([
            'path' => '/var/www/does-not-exist',
            'url' => 'http://stage.example.com',
            'screen_session' => 'alpha',
            'serve_port' => 8081,
        ]);

        $this->actingAs($admin)
            ->get(route('instances.show', $instance))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('instance.url', 'http://stage.example.com')
                ->where('instance.tunnel_url', 'http://localhost:8081')
            );
    }

    public function test_instance_list_offers_the_same_link(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Instance::factory()->create([
            'path' => '/var/www/does-not-exist',
            'screen_session' => 'alpha',
            'serve_port' => 8081,
        ]);

        $this->actingAs($admin)
            ->get(route('instances.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('instances.0.tunnel_url', 'http://localhost:8081')
            );
    }

    public function test_instance_without_a_serve_port_has_no_tunnel_link(): void
    {
        $instance = Instance::factory()->create(['serve_port' => null]);

        $this->assertNull($instance->tunnelUrl());
    }

    public function test_empty_template_removes_the_link_everywhere(): void
    {
        config(['deployer.tunnel_url_template' => '']);

        $instance = Instance::factory()->create(['screen_session' => 'alpha', 'serve_port' => 8081]);

        $this->assertNull($instance->tunnelUrl());
    }

    public function test_template_is_taken_from_the_config(): void
    {
        config(['deployer.tunnel_url_template' => 'https://{port}.tunnel.local']);

        $instance = Instance::factory()->create(['screen_session' => 'alpha', 'serve_port' => 9000]);

        $this->assertSame('https://9000.tunnel.local', $instance->tunnelUrl());
    }
}
