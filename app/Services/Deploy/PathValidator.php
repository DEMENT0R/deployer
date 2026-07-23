<?php

namespace App\Services\Deploy;

use App\Exceptions\PathValidationException;
use App\Models\Instance;

class PathValidator
{
    /**
     * @return non-empty-string
     */
    public function resolve(Instance $instance): string
    {
        $path = $instance->path;

        if ($path === '' || str_contains($path, '..')) {
            throw new PathValidationException('Invalid instance path.');
        }

        if (! is_dir($path)) {
            throw new PathValidationException("Instance path does not exist: {$path}");
        }

        $realPath = realpath($path);

        if ($realPath === false) {
            throw new PathValidationException("Unable to resolve instance path: {$path}");
        }

        $prefixes = $this->allowedPrefixes($instance);

        foreach ($prefixes as $prefix) {
            $normalizedPrefix = rtrim(str_replace('\\', '/', $prefix), '/');
            $normalizedPath = str_replace('\\', '/', $realPath);

            if ($normalizedPath === $normalizedPrefix || str_starts_with($normalizedPath.'/', $normalizedPrefix.'/')) {
                return $realPath;
            }
        }

        throw new PathValidationException('Instance path is not within allowed prefixes.');
    }

    /**
     * @return list<string>
     */
    private function allowedPrefixes(Instance $instance): array
    {
        $prefixes = config('deployer.allowed_path_prefixes', []);

        if ($instance->allowed_path_prefix) {
            $prefixes[] = $instance->allowed_path_prefix;
        }

        return array_values(array_unique(array_filter($prefixes)));
    }
}
