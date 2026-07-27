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
