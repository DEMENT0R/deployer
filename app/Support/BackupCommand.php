<?php

namespace App\Support;

class BackupCommand
{
    private const SCRIPT = 'scripts/backup-db.sh';

    /**
     * Команда шага backup, которую панель предлагает новому инстансу. Скрипт лежит рядом
     * с самой панелью: инстансы деплоятся на её же машину, поэтому путь известен и ставить
     * что-то на хост отдельно не нужно.
     *
     * Пустой `backup_root` означает «подсказки нет» — поле останется пустым, шаг пропустится.
     */
    public static function suggested(): string
    {
        $root = (string) config('deployer.backup_root');

        if ($root === '') {
            return '';
        }

        return sprintf(
            'bash %s --root=%s --keep=%d',
            self::quote(base_path(self::SCRIPT)),
            self::quote($root),
            (int) config('deployer.backup_keep'),
        );
    }

    /** Кавычки только там, где без них шелл разберёт путь неверно: команду читает человек. */
    private static function quote(string $value): string
    {
        return preg_match('/^[\w@%+=:,.\/-]+$/', $value) ? $value : escapeshellarg($value);
    }
}
