<?php

namespace Tests\Unit;

use App\Services\Deploy\GitService;
use App\Services\Deploy\ProcessResult;
use App\Services\Deploy\ProcessRunner;
use Tests\TestCase;

class GitServiceTest extends TestCase
{
    public function test_stashes_local_changes_before_checkout(): void
    {
        $runner = $this->recordingRunner(status: " M app/Foo.php\n?? bar.txt\n");

        (new GitService($runner))->deployBranch('/tmp/project', 'origin', 'feature/x');

        $this->assertSame([
            ['git', 'fetch', '--all'],
            ['git', 'status', '--porcelain'],
            ['git', 'stash', 'push', '--include-untracked'],
            ['git', 'checkout', 'feature/x'],
            ['git', 'pull', 'origin', 'feature/x'],
        ], $this->normalize($runner->commands));
    }

    public function test_skips_stash_when_tree_is_clean(): void
    {
        $runner = $this->recordingRunner(status: '');

        (new GitService($runner))->deployBranch('/tmp/project', 'origin', 'main');

        $this->assertSame([
            ['git', 'fetch', '--all'],
            ['git', 'status', '--porcelain'],
            ['git', 'checkout', 'main'],
            ['git', 'pull', 'origin', 'main'],
        ], $this->normalize($runner->commands));
    }

    public function test_skips_stash_when_auto_stash_disabled(): void
    {
        config(['deployer.auto_stash' => false]);

        $runner = $this->recordingRunner(status: " M app/Foo.php\n");

        (new GitService($runner))->deployBranch('/tmp/project', 'origin', 'main');

        $this->assertSame([
            ['git', 'fetch', '--all'],
            ['git', 'checkout', 'main'],
            ['git', 'pull', 'origin', 'main'],
        ], $this->normalize($runner->commands));
    }

    public function test_head_commit_is_split_on_the_field_separator(): void
    {
        $runner = $this->scriptedRunner([
            "abcdef1234567890\x1fabcdef1\x1fFix the thing\x1fAda Lovelace\x1f2026-07-27T10:00:00+00:00\n",
        ]);

        $this->assertSame([
            'sha' => 'abcdef1234567890',
            'short_sha' => 'abcdef1',
            'subject' => 'Fix the thing',
            'author' => 'Ada Lovelace',
            'committed_at' => '2026-07-27T10:00:00+00:00',
        ], (new GitService($runner))->headCommit('/tmp/project'));
    }

    public function test_head_commit_is_null_in_a_repository_without_commits(): void
    {
        $runner = $this->scriptedRunner([''], successful: false);

        $this->assertNull((new GitService($runner))->headCommit('/tmp/project'));
    }

    public function test_divergence_reads_ahead_and_behind(): void
    {
        $runner = $this->scriptedRunner(["3\t7\n"]);

        $git = new GitService($runner);

        $this->assertSame(['ahead' => 3, 'behind' => 7], $git->divergence('/tmp/project', 'origin', 'main'));
        $this->assertSame(
            [['git', 'rev-list', '--left-right', '--count', 'HEAD...origin/main']],
            $runner->commands,
        );
    }

    public function test_divergence_is_null_without_a_remote_tracking_branch(): void
    {
        $runner = $this->scriptedRunner(["fatal: ambiguous argument\n"], successful: false);

        $this->assertNull((new GitService($runner))->divergence('/tmp/project', 'origin', 'main'));
    }

    public function test_stash_count_ignores_blank_lines(): void
    {
        $runner = $this->scriptedRunner(["stash@{0}: WIP on main\nstash@{1}: WIP on main\n\n"]);

        $this->assertSame(2, (new GitService($runner))->stashCount('/tmp/project'));
    }

    public function test_stash_count_is_zero_for_an_empty_stash(): void
    {
        $runner = $this->scriptedRunner(['']);

        $this->assertSame(0, (new GitService($runner))->stashCount('/tmp/project'));
    }

    /**
     * @param  list<string>  $outputs
     */
    private function scriptedRunner(array $outputs, bool $successful = true): ProcessRunner
    {
        return new class($outputs, $successful) extends ProcessRunner
        {
            /** @var list<list<string>> */
            public array $commands = [];

            /**
             * @param  list<string>  $outputs
             */
            public function __construct(private array $outputs, private readonly bool $successful) {}

            public function run(array $command, string $cwd, ?\Closure $onOutput = null, ?int $timeout = null): ProcessResult
            {
                $this->commands[] = $command;

                return new ProcessResult(
                    successful: $this->successful,
                    output: array_shift($this->outputs) ?? '',
                    errorOutput: '',
                    exitCode: $this->successful ? 0 : 128,
                );
            }
        };
    }

    private function recordingRunner(string $status): ProcessRunner
    {
        return new class($status) extends ProcessRunner
        {
            /** @var list<list<string>> */
            public array $commands = [];

            public function __construct(private readonly string $status) {}

            public function run(array $command, string $cwd, ?\Closure $onOutput = null, ?int $timeout = null): ProcessResult
            {
                $this->commands[] = $command;

                $output = $command === ['git', 'status', '--porcelain'] ? $this->status : '';

                return new ProcessResult(successful: true, output: $output, errorOutput: '', exitCode: 0);
            }
        };
    }

    /**
     * The auto-stash message carries a timestamp; drop it so assertions stay stable.
     *
     * @param  list<list<string>>  $commands
     * @return list<list<string>>
     */
    private function normalize(array $commands): array
    {
        return array_map(function (array $command): array {
            $index = array_search('--message', $command, true);

            return $index === false ? $command : array_slice($command, 0, $index);
        }, $commands);
    }
}
