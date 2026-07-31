<?php

namespace Tests\Unit;

use App\Exceptions\PathValidationException;
use App\Models\Instance;
use App\Services\Deploy\PathValidator;
use Tests\TestCase;

class PathValidatorCloneTest extends TestCase
{
    private ?string $base = null;

    protected function tearDown(): void
    {
        if ($this->base !== null && is_dir($this->base)) {
            @rmdir($this->base);
        }

        parent::tearDown();
    }

    public function test_accepts_a_missing_target_inside_an_allowed_prefix(): void
    {
        $this->base = $this->tempDir();
        $target = $this->base.'/fresh-clone';

        config(['deployer.allowed_path_prefixes' => [$this->base]]);
        $instance = Instance::factory()->make(['path' => $target, 'allowed_path_prefix' => null]);

        $this->assertSame($target, (new PathValidator)->resolveForClone($instance));
    }

    /**
     * Заранее заведённый пустой каталог — штатный сценарий, когда воркеру нельзя писать
     * в родителя: права выдают на сам каталог назначения.
     */
    public function test_accepts_an_existing_empty_target(): void
    {
        $this->base = $this->tempDir();

        config(['deployer.allowed_path_prefixes' => [dirname($this->base)]]);
        $instance = Instance::factory()->make(['path' => $this->base, 'allowed_path_prefix' => null]);

        $this->assertSame($this->base, (new PathValidator)->resolveForClone($instance));
    }

    public function test_rejects_a_target_outside_allowed_prefixes(): void
    {
        $this->base = $this->tempDir();

        config(['deployer.allowed_path_prefixes' => ['/var/www']]);
        $instance = Instance::factory()->make([
            'path' => $this->base.'/clone',
            'allowed_path_prefix' => null,
        ]);

        $this->expectException(PathValidationException::class);
        (new PathValidator)->resolveForClone($instance);
    }

    public function test_rejects_a_non_empty_target(): void
    {
        $this->base = $this->tempDir();
        file_put_contents($this->base.'/keep.txt', 'x');

        config(['deployer.allowed_path_prefixes' => [dirname($this->base)]]);
        $instance = Instance::factory()->make(['path' => $this->base, 'allowed_path_prefix' => null]);

        try {
            $this->expectException(PathValidationException::class);
            (new PathValidator)->resolveForClone($instance);
        } finally {
            @unlink($this->base.'/keep.txt');
        }
    }

    public function test_rejects_traversal(): void
    {
        config(['deployer.allowed_path_prefixes' => ['/var/www']]);
        $instance = Instance::factory()->make(['path' => '/var/www/../etc', 'allowed_path_prefix' => null]);

        $this->expectException(PathValidationException::class);
        (new PathValidator)->resolveForClone($instance);
    }

    private function tempDir(): string
    {
        $dir = sys_get_temp_dir().'/deployer-clone-'.uniqid();
        mkdir($dir, 0755, true);

        return $dir;
    }
}
