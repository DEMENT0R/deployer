<?php

namespace App\Support;

use Illuminate\Support\Str;

class Changelog
{
    private const PATH = 'CHANGELOG.md';

    /**
     * Дата самой свежей секции — без разбора markdown, только для сравнения с отметкой
     * пользователя в общем пропсе на каждый запрос.
     */
    public static function latestDate(): ?string
    {
        return preg_match('/^## (\d{4}-\d{2}-\d{2})/m', self::read(), $matches) ? $matches[1] : null;
    }

    /**
     * @return array<int, array{date: string, html: string}>
     */
    public static function entries(): array
    {
        preg_match_all(
            '/^## (\d{4}-\d{2}-\d{2})\s*\n(.*?)(?=^## \d{4}-\d{2}-\d{2}|\z)/ms',
            self::read(),
            $matches,
            PREG_SET_ORDER,
        );

        return array_map(fn (array $match) => [
            'date' => $match[1],
            'html' => Str::markdown(trim($match[2])),
        ], $matches);
    }

    private static function read(): string
    {
        $path = base_path(self::PATH);

        return file_exists($path) ? file_get_contents($path) : '';
    }
}
