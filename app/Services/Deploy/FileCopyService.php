<?php

namespace App\Services\Deploy;

use App\Exceptions\DeployException;
use Closure;

/**
 * Копирование рабочей копии инстанса в каталог нового инстанса (action=copy).
 *
 * Только Linux: копируем через rsync. На Windows действие недоступно — см. InstanceDeployer::runCopy.
 */
class FileCopyService
{
    /**
     * Значения, которые нельзя тащить в копию: иначе она смотрит в базу и по адресу оригинала,
     * а миграция в копии ломает исходный стенд. Обнуляем, чтобы админ заполнил их сам.
     *
     * @var list<string>
     */
    public const ENV_BLANKED_KEYS = ['DB_DATABASE', 'APP_URL'];

    public function __construct(
        private readonly ProcessRunner $runner,
    ) {}

    public function copyTree(string $source, string $target, ?Closure $onOutput = null): void
    {
        $command = ['rsync', '-a', '--human-readable', '--stats'];

        foreach ($this->excludes() as $exclude) {
            $command[] = '--exclude';
            $command[] = $exclude;
        }

        // Слэш в конце источника: rsync копирует содержимое каталога, а не сам каталог внутрь target.
        $command[] = rtrim($source, '/').'/';
        $command[] = rtrim($target, '/').'/';

        $this->runner->runOrFail($command, dirname(rtrim($target, '/')), $onOutput);
    }

    /**
     * .env копии — тот же файл с обнулёнными ENV_BLANKED_KEYS. Правим построчно, а не
     * пересобираем из разобранных пар: порядок, комментарии и кавычки должны остаться как были.
     */
    public function seedEnv(string $source, string $target, ?Closure $onOutput = null): void
    {
        $sourceEnv = rtrim($source, '/').'/.env';

        if (! is_file($sourceEnv)) {
            if ($onOutput) {
                $onOutput("[deployer] Source has no .env, nothing to copy.\n");
            }

            return;
        }

        $contents = file_get_contents($sourceEnv);

        if ($contents === false) {
            throw new DeployException("Failed to read {$sourceEnv}.");
        }

        $written = file_put_contents(rtrim($target, '/').'/.env', $this->blankKeys($contents));

        if ($written === false) {
            throw new DeployException('Failed to write .env into the copy.');
        }

        if ($onOutput) {
            $onOutput('[deployer] .env copied, '.implode(' and ', self::ENV_BLANKED_KEYS)." left empty.\n");
        }
    }

    public function blankKeys(string $contents): string
    {
        foreach (self::ENV_BLANKED_KEYS as $key) {
            // Хвост берём как [^\r\n]*, а не .*$ — иначе на CRLF-файле «конец строки» не совпадёт.
            $pattern = '/^([ \t]*(?:export[ \t]+)?'.preg_quote($key, '/').'[ \t]*=)[^\r\n]*/m';

            $contents = preg_replace($pattern, '$1', $contents) ?? $contents;
        }

        return $contents;
    }

    /**
     * .env исключаем всегда, независимо от настройки: он уезжает отдельно и с правками (см. seedEnv).
     *
     * @return list<string>
     */
    private function excludes(): array
    {
        /** @var list<string> $configured */
        $configured = config('deployer.copy_excludes', []);

        return array_values(array_unique(array_merge(['.env'], $configured)));
    }
}
