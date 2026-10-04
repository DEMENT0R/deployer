<?php

namespace App\Services;

use App\Exceptions\PathValidationException;
use App\Models\Instance;
use App\Services\Deploy\PathValidator;

/**
 * Сколько места занимает то, что копится на стенде само: логи, кэши, загрузки и дампы.
 *
 * vendor, node_modules и .git в usage() не входят: это десятки тысяч файлов, обход которых на каждом
 * открытии списка инстансов стоил бы секунды, а места они не «набирают» — их размер
 * меняется только вместе с зависимостями. Их считает dependencies(), по явному запросу.
 */
class InstanceDiskService
{
    /** Категория → каталог относительно корня инстанса. */
    private const AREAS = [
        'logs' => ['storage/logs'],
        'cache' => ['storage/framework/cache', 'storage/framework/views', 'storage/framework/sessions', 'bootstrap/cache'],
        'uploads' => ['storage/app'],
    ];

    /**
     * Потолок обхода на категорию: если в storage/app лежат сотни тысяч файлов, честный
     * размер нам дороже, чем отзывчивость списка. Превышение помечаем, а не скрываем.
     */
    private const MAX_FILES = 50000;

    /** node_modules легко переваливает за сотню тысяч файлов; сюда ходят по кнопке, можно дольше. */
    private const MAX_DEPENDENCY_FILES = 400000;

    /**
     * .git — сюда же: у долгоживущего стенда он бывает больше vendor (паки, reflog, stash'и),
     * а обходить его так же дорого.
     */
    private const DEPENDENCIES = ['vendor', 'node_modules', '.git'];

    public function __construct(
        private readonly PathValidator $pathValidator,
        private readonly InstanceBackupService $backups,
    ) {}

    /**
     * null — каталог инстанса недоступен; null у категории — для неё нечего считать.
     *
     * @return array<string, array{bytes: int, files: int, partial: bool}|null>|null
     */
    public function usage(Instance $instance): ?array
    {
        try {
            $base = rtrim(str_replace('\\', '/', $this->pathValidator->resolve($instance)), '/');
        } catch (PathValidationException) {
            return null;
        }

        $usage = [];

        foreach (self::AREAS as $area => $directories) {
            $usage[$area] = $this->measure(array_map(fn (string $dir) => $base.'/'.$dir, $directories));
        }

        $backupDirectory = $this->backups->directory($instance);
        $usage['backups'] = $backupDirectory === null ? null : $this->measure([$backupDirectory]);

        return $usage;
    }

    /**
     * @return array<string, array{bytes: int, files: int, partial: bool}|null>
     *
     * @throws PathValidationException
     */
    public function dependencies(Instance $instance): array
    {
        $base = rtrim(str_replace('\\', '/', $this->pathValidator->resolve($instance)), '/');

        $usage = [];

        foreach (self::DEPENDENCIES as $directory) {
            $usage[$directory] = $this->measure([$base.'/'.$directory], self::MAX_DEPENDENCY_FILES);
        }

        return $usage;
    }

    /**
     * Свободное место на разделах, где лежат инстансы и их дампы. Точку монтирования PHP
     * не сообщает, поэтому один раздел узнаём по совпадению объёма и остатка: два разных
     * раздела с одинаковыми до байта цифрами на практике не встречаются.
     *
     * @param  iterable<Instance>  $instances
     * @return list<array{path: string, free: int, total: int}>
     */
    public function volumes(iterable $instances): array
    {
        $paths = [];

        foreach ($instances as $instance) {
            try {
                $paths[] = $this->pathValidator->resolve($instance);
            } catch (PathValidationException) {
                // Недоступный инстанс в итог не попадает — о нём и так говорит его карточка.
            }

            $backups = $this->backups->directory($instance);

            if ($backups !== null) {
                $paths[] = $this->existingAncestor($backups);
            }
        }

        $volumes = [];

        foreach (array_unique(array_filter($paths)) as $path) {
            $free = @disk_free_space($path);
            $total = @disk_total_space($path);

            if ($free === false || $total === false) {
                continue;
            }

            $volumes[(int) $total.':'.(int) $free] ??= [
                'path' => str_replace('\\', '/', $path),
                'free' => (int) $free,
                'total' => (int) $total,
            ];
        }

        return array_values($volumes);
    }

    /** Каталога дампов может ещё не быть — раздел определяем по ближайшему существующему предку. */
    private function existingAncestor(string $path): ?string
    {
        while (! is_dir($path)) {
            $parent = dirname($path);

            if ($parent === $path) {
                return null;
            }

            $path = $parent;
        }

        return $path;
    }

    /**
     * @param  list<string>  $directories
     * @return array{bytes: int, files: int, partial: bool}|null
     */
    private function measure(array $directories, int $limit = self::MAX_FILES): ?array
    {
        $existing = array_values(array_filter($directories, 'is_dir'));

        if ($existing === []) {
            return null;
        }

        $bytes = 0;
        $files = 0;
        $partial = false;

        foreach ($existing as $directory) {
            try {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::LEAVES_ONLY,
                    \RecursiveIteratorIterator::CATCH_GET_CHILD,
                );

                foreach ($iterator as $file) {
                    if (! $file->isFile()) {
                        continue;
                    }

                    // .gitignore-заглушки Laravel кладёт в каждый каталог storage — это не данные.
                    if ($file->getFilename() === '.gitignore') {
                        continue;
                    }

                    $bytes += (int) $file->getSize();

                    if (++$files >= $limit) {
                        return ['bytes' => $bytes, 'files' => $files, 'partial' => true];
                    }
                }
            } catch (\UnexpectedValueException) {
                // Каталог без прав на чтение: считаем то, до чего дотянулись, но не выдаём за полный размер.
                $partial = true;
            }
        }

        return ['bytes' => $bytes, 'files' => $files, 'partial' => $partial];
    }
}
