<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class InstanceDiskTest extends TestCase
{
    use RefreshDatabase;

    private ?string $base = null;

    protected function tearDown(): void
    {
        if ($this->base !== null) {
            File::deleteDirectory($this->base);
        }

        parent::tearDown();
    }

    public function test_index_does_not_walk_the_disk_on_the_first_render(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Instance::factory()->create();

        $this->actingAs($admin)
            ->get(route('instances.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->missing('disk'));
    }

    public function test_index_reports_logs_caches_uploads_and_dumps(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->base = sys_get_temp_dir().'/deployer-disk-'.uniqid();
        $stand = $this->base.'/www/stand';
        $this->writeFile($stand.'/storage/logs/laravel.log', 100);
        $this->writeFile($stand.'/storage/logs/.gitignore', 14);
        $this->writeFile($stand.'/storage/framework/views/a.php', 30);
        $this->writeFile($stand.'/bootstrap/cache/config.php', 20);
        $this->writeFile($stand.'/storage/app/public/avatar.png', 50);
        // vendor считать не должны: он не копится, а обходить его дорого.
        $this->writeFile($stand.'/vendor/autoload.php', 1000);
        $this->writeFile($this->base.'/dumps/stand/one.sql.gz', 200);
        $this->writeFile($this->base.'/dumps/stand/two.sql.gz', 300);

        config(['deployer.allowed_path_prefixes' => [$this->base]]);

        $instance = Instance::factory()->create([
            'path' => $stand,
            'allowed_path_prefix' => $this->base,
            'backup_command' => 'bash scripts/backup-db.sh --root='.$this->base.'/dumps',
        ]);
        $missing = Instance::factory()->create(['path' => '/var/www/does-not-exist']);

        $disk = $this->disk($admin);

        $this->assertSame(['bytes' => 100, 'files' => 1, 'partial' => false], $disk[$instance->id]['logs']);
        $this->assertSame(['bytes' => 50, 'files' => 2, 'partial' => false], $disk[$instance->id]['cache']);
        $this->assertSame(['bytes' => 50, 'files' => 1, 'partial' => false], $disk[$instance->id]['uploads']);
        $this->assertSame(['bytes' => 500, 'files' => 2, 'partial' => false], $disk[$instance->id]['backups']);
        $this->assertNull($disk[$missing->id]);
    }

    public function test_dumps_are_unknown_when_backups_are_not_taken_by_our_script(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->base = sys_get_temp_dir().'/deployer-disk-'.uniqid();
        $this->writeFile($this->base.'/storage/logs/laravel.log', 10);
        config(['deployer.allowed_path_prefixes' => [$this->base]]);

        $instance = Instance::factory()->create([
            'path' => $this->base,
            'allowed_path_prefix' => $this->base,
            'backup_command' => 'mysqldump app > /tmp/app.sql',
        ]);

        $disk = $this->disk($admin);

        $this->assertNull($disk[$instance->id]['backups']);
        $this->assertNull($disk[$instance->id]['uploads']);
        $this->assertSame(10, $disk[$instance->id]['logs']['bytes']);
    }

    public function test_vendor_and_node_modules_are_counted_on_request(): void
    {
        $this->base = sys_get_temp_dir().'/deployer-disk-'.uniqid();
        $this->writeFile($this->base.'/vendor/autoload.php', 40);
        $this->writeFile($this->base.'/vendor/laravel/framework/src/App.php', 60);
        config(['deployer.allowed_path_prefixes' => [$this->base]]);

        $instance = Instance::factory()->create(['path' => $this->base, 'allowed_path_prefix' => $this->base]);
        $tester = User::factory()->create(['role' => UserRole::Tester]);
        $instance->users()->attach($tester);

        $this->actingAs($tester)
            ->getJson(route('instances.disk.dependencies', $instance))
            ->assertOk()
            ->assertExactJson([
                'vendor' => ['bytes' => 100, 'files' => 2, 'partial' => false],
                'node_modules' => null,
            ]);
    }

    public function test_dependencies_of_an_unreadable_instance_are_an_error(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $instance = Instance::factory()->create(['path' => '/var/www/does-not-exist']);

        $this->actingAs($admin)
            ->getJson(route('instances.disk.dependencies', $instance))
            ->assertUnprocessable()
            ->assertJsonStructure(['message']);
    }

    public function test_a_tester_without_access_cannot_count_dependencies(): void
    {
        $instance = Instance::factory()->create(['path' => '/var/www/does-not-exist']);
        $stranger = User::factory()->create(['role' => UserRole::Tester]);

        $this->actingAs($stranger)
            ->getJson(route('instances.disk.dependencies', $instance))
            ->assertForbidden();
    }

    private function disk(User $user): array
    {
        return $this->actingAs($user)
            ->get(route('instances.index'), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (new HandleInertiaRequests)->version(request()) ?? '',
                'X-Inertia-Partial-Component' => 'Instances/Index',
                'X-Inertia-Partial-Data' => 'disk',
            ])
            ->assertOk()
            ->json('props.disk');
    }

    private function writeFile(string $path, int $bytes): void
    {
        File::ensureDirectoryExists(dirname($path));
        file_put_contents($path, str_repeat('x', $bytes));
    }
}
