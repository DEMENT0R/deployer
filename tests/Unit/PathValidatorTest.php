<?php

namespace Tests\Unit;

use App\Exceptions\PathValidationException;
use App\Models\Instance;
use App\Services\Deploy\PathValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PathValidatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_path_with_parent_traversal(): void
    {
        config(['deployer.allowed_path_prefixes' => ['/var/www']]);

        $instance = Instance::factory()->make([
            'path' => '/var/www/../etc',
        ]);

        $this->expectException(PathValidationException::class);

        (new PathValidator)->resolve($instance);
    }

    public function test_rejects_path_outside_allowed_prefix(): void
    {
        config(['deployer.allowed_path_prefixes' => ['/var/www']]);

        $base = sys_get_temp_dir().'/deployer-test-'.uniqid();
        mkdir($base, 0755, true);

        $instance = Instance::factory()->make([
            'path' => $base,
            'allowed_path_prefix' => null,
        ]);

        try {
            $this->expectException(PathValidationException::class);
            (new PathValidator)->resolve($instance);
        } finally {
            rmdir($base);
        }
    }

    public function test_accepts_path_within_allowed_prefix(): void
    {
        $base = sys_get_temp_dir().'/deployer-www-'.uniqid();
        mkdir($base, 0755, true);

        config(['deployer.allowed_path_prefixes' => [$base]]);

        $instance = Instance::factory()->make([
            'path' => $base,
        ]);

        try {
            $resolved = (new PathValidator)->resolve($instance);
            $this->assertSame(realpath($base), $resolved);
        } finally {
            rmdir($base);
        }
    }
}
