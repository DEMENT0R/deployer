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
            // Каталог, закрытый от воркера по правам (обычное дело для /home/user на Linux),
            // отсюда неотличим от отсутствующего — называем оба варианта, иначе поиск причины
            // уходит не туда.
            throw new PathValidationException(
                "Instance path does not exist or is not readable by the web/worker user: {$path}"
            );
        }

        $realPath = realpath($path);

        if ($realPath === false) {
            throw new PathValidationException("Unable to resolve instance path: {$path}");
        }

        if ($this->withinAllowedPrefix(str_replace('\\', '/', $realPath), $instance)) {
            return $realPath;
        }

        throw new PathValidationException('Instance path is not within allowed prefixes.');
    }

    /**
     * Путь назначения для первичного clone: каталога ещё нет, поэтому realpath к нему
     * неприменим — префикс проверяем по нормализованной строке. Дополнительно требуем,
     * чтобы каталог был пуст (или отсутствовал), а родитель существовал — иначе git clone
     * либо упадёт, либо затрёт чужие файлы.
     *
     * @return non-empty-string
     */
    public function resolveForClone(Instance $instance): string
    {
        $path = $instance->path;

        if ($path === '' || str_contains($path, '..')) {
            throw new PathValidationException('Invalid instance path.');
        }

        $normalizedPath = rtrim(str_replace('\\', '/', $path), '/');

        if (! $this->withinAllowedPrefix($normalizedPath, $instance)) {
            throw new PathValidationException('Instance path is not within allowed prefixes.');
        }

        if (is_file($path)) {
            throw new PathValidationException("Target path is a file: {$path}");
        }

        // Пустой каталог назначения — рабочий случай, а не полумера: когда воркеру нельзя писать
        // в родителя (домашний каталог на Linux), каталог заводят руками и выдают права на него.
        // Тогда права на родителя не нужны — clone и rsync пишут внутрь существующего каталога.
        if (is_dir($path)) {
            if (! $this->isEmptyDir($path)) {
                throw new PathValidationException("Target path is not empty: {$path}");
            }

            if (! is_writable($path)) {
                throw new PathValidationException(
                    "The web/worker user cannot write into the target directory: {$path}"
                );
            }

            return $path;
        }

        $parent = dirname($path);

        if (! is_dir($parent)) {
            throw new PathValidationException(
                "Parent directory does not exist or is not readable by the web/worker user: {$parent}"
            );
        }

        // Каталог назначения создаёт git clone или rsync, то есть тот же пользователь, под которым
        // крутится воркер. Проверяем заранее: иначе падение приходит из середины подпроцесса.
        if (! is_writable($parent)) {
            throw new PathValidationException(
                "The web/worker user cannot create the target directory in {$parent}."
            );
        }

        return $path;
    }

    private function withinAllowedPrefix(string $normalizedPath, Instance $instance): bool
    {
        foreach ($this->allowedPrefixes($instance) as $prefix) {
            $normalizedPrefix = rtrim(str_replace('\\', '/', $prefix), '/');

            if ($normalizedPath === $normalizedPrefix || str_starts_with($normalizedPath.'/', $normalizedPrefix.'/')) {
                return true;
            }
        }

        return false;
    }

    private function isEmptyDir(string $path): bool
    {
        $entries = @scandir($path);

        if ($entries === false) {
            return false;
        }

        return count(array_diff($entries, ['.', '..'])) === 0;
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
