<?php

namespace App\Services\Deploy;

use App\Models\Instance;
use Illuminate\Support\Facades\Cache;

class GitBranchResolver
{
    public function __construct(
        private readonly PathValidator $pathValidator,
        private readonly GitService $gitService,
    ) {}

    /**
     * @return array{branches: list<string>, current: ?string}
     */
    public function resolve(Instance $instance, bool $useCache = true): array
    {
        $cacheKey = "instance:{$instance->id}:branches";
        $ttl = config('deployer.branch_cache_ttl', 300);

        if ($useCache) {
            return Cache::remember($cacheKey, $ttl, fn () => $this->fetch($instance));
        }

        $data = $this->fetch($instance);
        Cache::put($cacheKey, $data, $ttl);

        return $data;
    }

    /**
     * @return array{branches: list<string>, current: ?string}
     */
    public function refresh(Instance $instance): array
    {
        $cwd = $this->pathValidator->resolve($instance);
        $this->gitService->fetchAll($cwd);

        return $this->resolve($instance, useCache: false);
    }

    public function forget(Instance $instance): void
    {
        Cache::forget("instance:{$instance->id}:branches");
    }

    /**
     * @return array{branches: list<string>, current: ?string}
     */
    private function fetch(Instance $instance): array
    {
        $cwd = $this->pathValidator->resolve($instance);

        return [
            'branches' => $this->gitService->remoteBranches($cwd, $instance->git_remote),
            'current' => $this->gitService->currentBranch($cwd),
        ];
    }
}
