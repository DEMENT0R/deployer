<?php

namespace App\Services;

use App\Enums\DeployAction;
use App\Exceptions\PathValidationException;
use App\Models\Deployment;
use App\Services\Deploy\GitService;
use App\Services\Deploy\PathValidator;
use Throwable;

/**
 * Что уехало в деплой: коммиты между состоянием рабочего дерева до и после.
 *
 * Считается по локальным объектам репозитория, в сеть не ходит. Любая проблема
 * возвращается статусом, а не исключением — вызывающая страница должна открываться всегда.
 */
class DeploymentCommitsService
{
    /** Сколько коммитов максимум отдавать наружу. */
    private const LIMIT = 100;

    public function __construct(
        private readonly PathValidator $pathValidator,
        private readonly GitService $gitService,
    ) {}

    /**
     * @return array{status: string, message: ?string, direction: ?string, truncated: bool, commits: list<array<string, mixed>>}
     */
    public function forDeployment(Deployment $deployment): array
    {
        $before = $deployment->commit_before;
        $after = $deployment->commit_after;

        // Деплой либо ещё не дошёл до git-шага, либо прошёл до появления этих полей.
        if (blank($before) || blank($after)) {
            return $this->failure('unknown', 'This deployment did not record the commits it moved between.');
        }

        if ($before === $after) {
            return $this->failure('unchanged', 'The working tree did not move: no new commits.');
        }

        // Откат двигает дерево назад, поэтому интересен обратный диапазон — что откатили.
        $reverted = $deployment->action === DeployAction::Rollback;
        [$from, $to] = $reverted ? [$after, $before] : [$before, $after];

        try {
            $cwd = $this->pathValidator->resolve($deployment->instance);
        } catch (PathValidationException $exception) {
            return $this->failure('path_error', $exception->getMessage());
        }

        try {
            $commits = $this->gitService->commitsBetween($cwd, $from, $to, self::LIMIT);
        } catch (Throwable $exception) {
            report($exception);

            return $this->failure(
                'git_error',
                trim($exception->getMessage()) ?: 'Failed to read the commit range.',
            );
        }

        return [
            'status' => 'ok',
            'message' => null,
            'direction' => $reverted ? 'reverted' : 'deployed',
            'truncated' => count($commits) >= self::LIMIT,
            'commits' => $commits,
        ];
    }

    /**
     * @return array{status: string, message: string, direction: null, truncated: false, commits: array{}}
     */
    private function failure(string $status, string $message): array
    {
        return [
            'status' => $status,
            'message' => $message,
            'direction' => null,
            'truncated' => false,
            'commits' => [],
        ];
    }
}
