<?php

namespace Tests\Unit;

use App\Services\Deploy\ProcessResult;
use App\Services\Deploy\ProcessRunner;
use App\Services\ScreenMonitorService;
use Tests\TestCase;

class ScreenMonitorServiceTest extends TestCase
{
    private const PS_OUTPUT = <<<'OUT'
      1201 root                              90061 SCREEN -dmS backups
      2410 mbezrukavnikov                     3605 SCREEN -S api
      2455 mbezrukavnikov                     3600 screen -r api
      2712 mbezrukavnikov                       45 SCREEN
      3001 deploy                              120 SCREEN -dmS worker-2

    OUT;

    public function test_parses_sessions_and_hides_root_by_default(): void
    {
        $snapshot = $this->service(self::PS_OUTPUT)->snapshot();

        $this->assertTrue($snapshot['available']);
        $this->assertSame(['deploy', 'mbezrukavnikov', 'mbezrukavnikov'], array_column($snapshot['sessions'], 'user'));
        $this->assertSame(1, $snapshot['hidden_count']);
        $this->assertSame(['root'], $snapshot['hidden_users']);

        $api = $snapshot['sessions'][1];
        $this->assertSame(2410, $api['pid']);
        $this->assertSame('api', $api['name']);
        $this->assertSame(3605, $api['uptime_seconds']);
    }

    public function test_client_processes_are_not_listed_as_sessions(): void
    {
        $sessions = $this->service(self::PS_OUTPUT)->snapshot()['sessions'];

        // 2455 — это `screen -r`, клиент строчными буквами, а не сама сессия.
        $this->assertNotContains(2455, array_column($sessions, 'pid'));
    }

    public function test_session_without_dash_s_has_no_name(): void
    {
        $sessions = $this->service(self::PS_OUTPUT)->snapshot()['sessions'];
        $unnamed = collect($sessions)->firstWhere('pid', 2712);

        $this->assertNotNull($unnamed);
        $this->assertNull($unnamed['name']);
    }

    public function test_users_list_keeps_hidden_users_with_counts(): void
    {
        $users = $this->service(self::PS_OUTPUT)->snapshot()['users'];

        $this->assertSame(
            [
                ['name' => 'deploy', 'count' => 1, 'hidden_by_default' => false],
                ['name' => 'mbezrukavnikov', 'count' => 2, 'hidden_by_default' => false],
                ['name' => 'root', 'count' => 1, 'hidden_by_default' => true],
            ],
            $users,
        );
    }

    public function test_filter_shows_a_hidden_user_when_asked_explicitly(): void
    {
        $snapshot = $this->service(self::PS_OUTPUT)->snapshot('root');

        $this->assertCount(1, $snapshot['sessions']);
        $this->assertSame('backups', $snapshot['sessions'][0]['name']);
        $this->assertSame('root', $snapshot['filter']);
    }

    public function test_no_matches_is_an_empty_list_not_an_error(): void
    {
        // `ps -C` без единого совпадения выходит с кодом 1 и ничего не пишет.
        $snapshot = $this->service('', successful: false)->snapshot();

        $this->assertTrue($snapshot['available']);
        $this->assertSame([], $snapshot['sessions']);
    }

    public function test_missing_ps_is_reported_as_unavailable(): void
    {
        $snapshot = $this->service('', successful: false, errorOutput: 'ps: command not found')->snapshot();

        $this->assertFalse($snapshot['available']);
        $this->assertSame('ps: command not found', $snapshot['message']);
    }

    private function service(string $output, bool $successful = true, string $errorOutput = ''): ScreenMonitorService
    {
        config(['deployer.screen_hidden_users' => ['root']]);

        $runner = new class($output, $successful, $errorOutput) extends ProcessRunner
        {
            public function __construct(
                private readonly string $output,
                private readonly bool $successful,
                private readonly string $errorOutput,
            ) {}

            public function run(array $command, string $cwd, ?\Closure $onOutput = null, ?int $timeout = null): ProcessResult
            {
                return new ProcessResult(
                    successful: $this->successful,
                    output: $this->output,
                    errorOutput: $this->errorOutput,
                    exitCode: $this->successful ? 0 : 1,
                );
            }
        };

        return new class($runner) extends ScreenMonitorService
        {
            protected function supported(): bool
            {
                return true;
            }
        };
    }
}
