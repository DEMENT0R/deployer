<?php

namespace Tests\Unit;

use App\Services\Deploy\GitService;
use App\Services\Deploy\ProcessResult;
use App\Services\Deploy\ProcessRunner;
use Closure;
use Tests\TestCase;

class GitRemoteBranchesTest extends TestCase
{
    public function test_keeps_the_order_git_returned_and_carries_dates(): void
    {
        // Порядок намеренно не алфавитный: так видно, что PHP его не пересортировывает.
        $output = implode("\n", [
            "origin/feature/late\x1f2026-07-30T10:00:00+00:00",
            "origin/HEAD\x1f2026-07-29T10:00:00+00:00",
            "origin/main\x1f2026-07-29T10:00:00+00:00",
            "origin/aaa-old\x1f2026-01-01T10:00:00+03:00",
        ]);

        $runner = $this->cannedRunner($output, true, $captured);

        $branches = (new GitService($runner))->remoteBranches('/var/www/app', 'origin');

        // origin/HEAD — символическая ссылка, её в списке быть не должно.
        $this->assertSame(
            ['feature/late', 'main', 'aaa-old'],
            array_column($branches, 'name'),
        );

        $this->assertSame('2026-07-30T10:00:00+00:00', $branches[0]['committed_at']);
        // Смещение таймзоны сохраняем как отдал git — разбирает его уже браузер.
        $this->assertSame('2026-01-01T10:00:00+03:00', $branches[2]['committed_at']);

        $this->assertContains('--sort=-committerdate', $captured);
        $this->assertContains('refs/remotes/origin/', $captured);
    }

    public function test_strips_only_the_configured_remote_prefix(): void
    {
        $output = "upstream/main\x1f2026-07-30T10:00:00+00:00";

        $runner = $this->cannedRunner($output, true, $captured);

        $branches = (new GitService($runner))->remoteBranches('/var/www/app', 'upstream');

        $this->assertSame('main', $branches[0]['name']);
        $this->assertContains('refs/remotes/upstream/', $captured);
    }

    public function test_survives_a_line_without_a_date(): void
    {
        $runner = $this->cannedRunner("origin/main\norigin/dev\x1f2026-07-30T10:00:00+00:00", true, $captured);

        $branches = (new GitService($runner))->remoteBranches('/var/www/app', 'origin');

        $this->assertSame(['main', 'dev'], array_column($branches, 'name'));
        $this->assertNull($branches[0]['committed_at']);
    }

    public function test_empty_output_yields_no_branches(): void
    {
        $runner = $this->cannedRunner('', true, $captured);

        $this->assertSame([], (new GitService($runner))->remoteBranches('/var/www/app', 'origin'));
    }

    /**
     * ProcessRunner с заранее заданным выводом: git не запускается, команда запоминается.
     */
    private function cannedRunner(string $output, bool $successful, ?array &$captured): ProcessRunner
    {
        return new class($output, $successful, $captured) extends ProcessRunner
        {
            public function __construct(
                private string $cannedOutput,
                private bool $ok,
                private ?array &$captured,
            ) {}

            public function run(array $command, string $cwd, ?Closure $onOutput = null, ?int $timeout = null): ProcessResult
            {
                $this->captured = $command;

                return new ProcessResult(
                    successful: $this->ok,
                    output: $this->cannedOutput,
                    errorOutput: '',
                    exitCode: 0,
                );
            }
        };
    }
}
