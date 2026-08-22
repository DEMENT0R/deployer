<?php

namespace App\Services;

use App\Exceptions\PathValidationException;
use App\Models\Instance;
use App\Services\Deploy\PathValidator;

/**
 * Хвост лога целевого проекта. Тестировщик видит на стенде 500 и без этого не может ничего,
 * кроме как идти к админу: доступа к файлам стенда у него нет.
 *
 * Читаем с конца и кусками: лог стенда легко бывает в сотни мегабайт, и целиком его в память
 * тянуть нельзя — ни ради последних строк, ни вообще.
 */
class InstanceLogService
{
    /** Сколько байтов с конца просматриваем максимум — потолок памяти и времени ответа. */
    private const MAX_BYTES = 524288;

    private const CHUNK = 8192;

    public function __construct(private readonly PathValidator $pathValidator) {}

    /**
     * @return array{path: string, exists: bool, size: int, modified_at: ?string, truncated: bool, lines: list<string>}
     *
     * @throws PathValidationException
     */
    public function tail(Instance $instance, ?int $lines = null): array
    {
        $lines ??= (int) config('deployer.log_tail_lines', 200);
        $path = $this->path($instance);

        if (! is_file($path)) {
            return [
                'path' => $path,
                'exists' => false,
                'size' => 0,
                'modified_at' => null,
                'truncated' => false,
                'lines' => [],
            ];
        }

        $size = (int) filesize($path);
        [$text, $truncated] = $this->readTail($path, $size, $lines);

        return [
            'path' => $path,
            'exists' => true,
            'size' => $size,
            'modified_at' => date(DATE_ATOM, (int) filemtime($path)),
            'truncated' => $truncated,
            'lines' => $text,
        ];
    }

    /**
     * Обнуляем файл, а не удаляем: удалённый лог php-fpm продолжит писать в открытый дескриптор,
     * и до перезапуска стенда новые записи будут уходить в никуда.
     *
     * @throws PathValidationException
     */
    public function clear(Instance $instance): bool
    {
        $path = $this->path($instance);

        if (! is_file($path)) {
            return false;
        }

        return file_put_contents($path, '') !== false;
    }

    /**
     * @throws PathValidationException
     */
    private function path(Instance $instance): string
    {
        $base = $this->pathValidator->resolve($instance);
        $file = ltrim((string) config('deployer.log_file', 'storage/logs/laravel.log'), '/\\');

        return rtrim(str_replace('\\', '/', $base), '/').'/'.$file;
    }

    /**
     * @return array{0: list<string>, 1: bool} Строки и признак «лог длиннее, чем показано»
     */
    private function readTail(string $path, int $size, int $lines): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return [[], false];
        }

        $offset = $size;
        $buffer = '';
        $found = 0;

        while ($offset > 0 && $found <= $lines && strlen($buffer) < self::MAX_BYTES) {
            $step = (int) min(self::CHUNK, $offset);
            $offset -= $step;

            fseek($handle, $offset);
            $chunk = (string) fread($handle, $step);

            $buffer = $chunk.$buffer;
            $found = substr_count($buffer, "\n");
        }

        fclose($handle);

        $all = preg_split('/\R/', rtrim($buffer, "\r\n")) ?: [];
        $tail = array_slice($all, -$lines);

        return [array_values($tail), $offset > 0 || count($all) > count($tail)];
    }
}
