<?php

namespace App\Services;

use App\Exceptions\PathValidationException;
use App\Models\Instance;
use App\Services\Deploy\GitService;
use App\Services\Deploy\PathValidator;
use Throwable;

/**
 * Снимок рабочего дерева целевого проекта: что там сейчас лежит на самом деле.
 *
 * Ни одна команда не ходит в сеть — ahead/behind считается по локальным remote-tracking
 * ссылкам, то есть настолько свежо, насколько свеж последний fetch. Любая проблема
 * возвращается статусом, а не исключением: страница инстанса должна открываться всегда.
 */
class InstanceStatusService
{
    public function __construct(
        private readonly PathValidator $pathValidator,
        private readonly GitService $gitService,
    ) {}

    /**
     * @return array{
     *     status: string,
     *     message: ?string,
     *     branch: ?string,
     *     tracking: ?string,
     *     ahead: ?int,
     *     behind: ?int,
     *     dirty: ?bool,
     *     changed_files: ?int,
     *     stashes: ?int,
     *     commit: null|array{sha: string, short_sha: string, subject: string, author: string, committed_at: ?string}
     * }
     */
    public function inspect(Instance $instance): array
    {
        try {
            $cwd = $this->pathValidator->resolve($instance);
        } catch (PathValidationException $exception) {
            return $this->failure('path_error', $exception->getMessage());
        }

        try {
            $branch = $this->gitService->currentBranch($cwd);
            $changed = $this->gitService->statusLines($cwd);
            $commit = $this->gitService->headCommit($cwd);
            $stashes = $this->gitService->stashCount($cwd);
            $divergence = $branch === null
                ? null
                : $this->gitService->divergence($cwd, $instance->git_remote, $branch);
        } catch (Throwable $exception) {
            report($exception);

            return $this->failure('git_error', trim($exception->getMessage()) ?: 'Failed to read git status.');
        }

        return [
            'status' => 'ok',
            'message' => null,
            'branch' => $branch,
            'tracking' => $branch === null ? null : "{$instance->git_remote}/{$branch}",
            'ahead' => $divergence['ahead'] ?? null,
            'behind' => $divergence['behind'] ?? null,
            'dirty' => $changed !== [],
            'changed_files' => count($changed),
            'stashes' => $stashes,
            'commit' => $commit,
        ];
    }

    /**
     * @return array{status: string, message: string, branch: null, tracking: null, ahead: null, behind: null, dirty: null, changed_files: null, stashes: null, commit: null}
     */
    private function failure(string $status, string $message): array
    {
        return [
            'status' => $status,
            'message' => $message,
            'branch' => null,
            'tracking' => null,
            'ahead' => null,
            'behind' => null,
            'dirty' => null,
            'changed_files' => null,
            'stashes' => null,
            'commit' => null,
        ];
    }
}
