<?php

namespace App\Services;

use App\Models\Instance;

/**
 * Что лежит в каталоге дампов инстанса. Каталог панель знает только тогда, когда бэкап
 * снимается её собственным scripts/backup-db.sh: команда шага — свободное поле, и куда
 * пишет чужая команда, не знает никто.
 */
class InstanceBackupService
{
    /** Сколько дампов показываем: список нужен для «откатиться на вчера», а не как архив. */
    private const LIMIT = 20;

    /** Имя дампа приходит из браузера — принимаем только то, что могло родиться в скрипте. */
    private const NAME_PATTERN = '/^[A-Za-z0-9._-]+\.sql\.gz$/';

    /**
     * Каталог дампов инстанса или null, если бэкап снимает не наш скрипт.
     */
    public function directory(Instance $instance): ?string
    {
        $command = (string) $instance->backup_command;

        if (! str_contains($command, 'backup-db.sh')) {
            return null;
        }

        [$root, $slug] = $this->parse($command);

        $root ??= (string) config('deployer.backup_root');

        if ($root === '') {
            return null;
        }

        $slug ??= basename(rtrim(str_replace('\\', '/', $instance->path), '/'));

        return rtrim(str_replace('\\', '/', $root), '/').'/'.$slug;
    }

    /**
     * @return list<array{name: string, size: int, modified_at: string}>
     */
    public function list(Instance $instance): array
    {
        $directory = $this->directory($instance);

        if ($directory === null || ! is_dir($directory)) {
            return [];
        }

        $files = glob($directory.'/*.sql.gz') ?: [];
        $dumps = [];

        foreach ($files as $file) {
            if (! is_file($file)) {
                continue;
            }

            $dumps[] = [
                'name' => basename($file),
                'size' => (int) filesize($file),
                'modified_at' => date(DATE_ATOM, (int) filemtime($file)),
            ];
        }

        usort($dumps, fn (array $a, array $b) => strcmp($b['modified_at'], $a['modified_at']));

        return array_slice($dumps, 0, self::LIMIT);
    }

    /**
     * Полный путь дампа по имени из браузера — null, если такого файла в каталоге нет.
     */
    public function resolve(Instance $instance, string $name): ?string
    {
        if (! preg_match(self::NAME_PATTERN, $name)) {
            return null;
        }

        $directory = $this->directory($instance);

        if ($directory === null) {
            return null;
        }

        $path = $directory.'/'.$name;

        return is_file($path) ? $path : null;
    }

    /**
     * Разбор своей же команды: скрипт кладёт дампы в `--root/<slug>`, а slug по умолчанию —
     * имя каталога инстанса.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function parse(string $command): array
    {
        $root = null;
        $slug = null;
        $afterScript = false;

        foreach (preg_split('/\s+/', trim($command)) ?: [] as $token) {
            if (! $afterScript) {
                $afterScript = str_contains($token, 'backup-db.sh');

                continue;
            }

            if (str_starts_with($token, '--root=')) {
                $root = trim(substr($token, 7), "\"'");
            } elseif (! str_starts_with($token, '-')) {
                $slug = trim($token, "\"'");
            }
        }

        return [$root, $slug];
    }
}
