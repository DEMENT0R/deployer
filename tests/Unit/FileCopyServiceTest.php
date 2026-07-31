<?php

namespace Tests\Unit;

use App\Services\Deploy\FileCopyService;
use App\Services\Deploy\ProcessResult;
use App\Services\Deploy\ProcessRunner;
use Closure;
use Tests\TestCase;

class FileCopyServiceTest extends TestCase
{
    public function test_copy_passes_directory_contents_and_configured_excludes(): void
    {
        config(['deployer.copy_excludes' => ['vendor/', 'node_modules/']]);

        $runner = $this->recordingRunner($captured);

        (new FileCopyService($runner))->copyTree('/var/www/app', '/var/www/copy');

        $this->assertSame('rsync', $captured[0]);
        // Слэш в конце источника — иначе rsync положит каталог внутрь каталога.
        $this->assertSame('/var/www/app/', $captured[count($captured) - 2]);
        $this->assertSame('/var/www/copy/', end($captured));
        $this->assertContains('vendor/', $captured);
        $this->assertContains('node_modules/', $captured);
    }

    public function test_env_is_always_excluded_from_the_rsync_pass(): void
    {
        config(['deployer.copy_excludes' => []]);

        $runner = $this->recordingRunner($captured);

        (new FileCopyService($runner))->copyTree('/var/www/app', '/var/www/copy');

        $this->assertContains('.env', $captured);
    }

    public function test_blanked_keys_lose_their_values(): void
    {
        $contents = <<<'ENV'
        APP_NAME=Laravel
        APP_URL=https://stage.example.com
        # comment
        DB_CONNECTION=mysql
        DB_DATABASE=stage_db
        DB_PASSWORD=secret
        ENV;

        $result = (new FileCopyService(new ProcessRunner))->blankKeys($contents);

        $this->assertStringContainsString("APP_URL=\n", $result);
        $this->assertStringContainsString("DB_DATABASE=\n", $result);
        $this->assertStringContainsString('APP_NAME=Laravel', $result);
        $this->assertStringContainsString('DB_PASSWORD=secret', $result);
        $this->assertStringContainsString('# comment', $result);
        $this->assertStringNotContainsString('stage_db', $result);
        $this->assertStringNotContainsString('stage.example.com', $result);
    }

    public function test_blanking_survives_export_prefix_and_crlf(): void
    {
        $result = (new FileCopyService(new ProcessRunner))
            ->blankKeys("export DB_DATABASE=stage_db\r\nAPP_NAME=Laravel\r\n");

        $this->assertStringContainsString("export DB_DATABASE=\r\n", $result);
        $this->assertStringContainsString("APP_NAME=Laravel\r\n", $result);
    }

    public function test_similar_key_names_are_left_alone(): void
    {
        $result = (new FileCopyService(new ProcessRunner))
            ->blankKeys("DB_DATABASE_URL=mysql://localhost/app\nSECOND_APP_URL=https://example.com\n");

        $this->assertStringContainsString('DB_DATABASE_URL=mysql://localhost/app', $result);
        $this->assertStringContainsString('SECOND_APP_URL=https://example.com', $result);
    }

    /**
     * ProcessRunner, который ничего не запускает, а лишь запоминает переданную команду.
     */
    private function recordingRunner(?array &$captured): ProcessRunner
    {
        return new class($captured) extends ProcessRunner
        {
            public function __construct(private ?array &$captured) {}

            public function runOrFail(array $command, string $cwd, ?Closure $onOutput = null, ?int $timeout = null): ProcessResult
            {
                $this->captured = $command;

                return new ProcessResult(successful: true, output: '', errorOutput: '', exitCode: 0);
            }
        };
    }
}
