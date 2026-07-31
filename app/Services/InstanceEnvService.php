<?php

namespace App\Services;

use App\Exceptions\EnvWriteException;
use App\Exceptions\PathValidationException;
use App\Models\Instance;
use App\Services\Deploy\PathValidator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Читает и правит .env целевого проекта из админки.
 *
 * Наружу уходят только ключи из `deployer.env_visible_keys`, значения чувствительных
 * ключей маскируются здесь же — незамаскированное значение не должно доехать до Inertia.
 * Правка ограничена тем же списком ключей: что админ видит, то и может менять.
 */
class InstanceEnvService
{
    private const EXAMPLE_FILE = '.env.example';

    public function __construct(private readonly PathValidator $pathValidator) {}

    /**
     * @return array{
     *     status: string,
     *     message: ?string,
     *     file: ?array{path: string, size: int, modified_at: ?string},
     *     variables: list<array{key: string, value: ?string, masked: bool, present: bool}>,
     *     hidden_count: int,
     *     example_available: bool
     * }
     */
    public function inspect(Instance $instance): array
    {
        try {
            $root = $this->pathValidator->resolve($instance);
        } catch (PathValidationException $exception) {
            return $this->failure('path_error', $exception->getMessage());
        }

        $path = $root.DIRECTORY_SEPARATOR.'.env';
        // Отсутствующий .env страница предлагает завести — и должна знать, есть ли из чего.
        $example = is_file($root.DIRECTORY_SEPARATOR.self::EXAMPLE_FILE);

        if (! is_file($path)) {
            return $this->failure('missing', ".env not found in {$root}", null, $example);
        }

        if (! is_readable($path)) {
            return $this->failure('unreadable', 'The web/worker user cannot read this .env.', null, $example);
        }

        $meta = $this->meta($path);
        $maxSize = (int) config('deployer.env_max_size');

        if ($meta['size'] > $maxSize) {
            return $this->failure(
                'too_large',
                "The .env file is larger than {$maxSize} bytes and was not parsed.",
                $meta,
                $example
            );
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return $this->failure('unreadable', 'Failed to read .env.', $meta, $example);
        }

        $parsed = $this->parse($contents);

        /** @var list<string> $visibleKeys */
        $visibleKeys = config('deployer.env_visible_keys', []);

        $variables = [];

        foreach ($visibleKeys as $key) {
            $present = array_key_exists($key, $parsed);
            $sensitive = $this->isSensitive($key);

            $variables[] = [
                'key' => $key,
                'value' => $present
                    ? ($sensitive ? $this->mask($parsed[$key]) : $parsed[$key])
                    : null,
                'masked' => $present && $sensitive,
                'present' => $present,
            ];
        }

        return [
            'status' => 'ok',
            'message' => null,
            'file' => $meta,
            'variables' => $variables,
            'hidden_count' => count(array_diff(array_keys($parsed), $visibleKeys)),
            'example_available' => $example,
        ];
    }

    /**
     * Заводит отсутствующий .env: пустым или копией .env.example. Существующий файл не трогаем —
     * перезапись «создающим» действием стоила бы боевых значений.
     *
     * @param  'blank'|'example'  $source
     *
     * @throws PathValidationException
     * @throws EnvWriteException
     */
    public function create(Instance $instance, string $source): void
    {
        $root = $this->pathValidator->resolve($instance);
        $path = $root.DIRECTORY_SEPARATOR.'.env';

        if (is_file($path)) {
            throw new EnvWriteException('.env already exists — delete it on the host first.');
        }

        if (! is_writable($root)) {
            throw new EnvWriteException("The web/worker user cannot write into {$root}.");
        }

        $contents = $source === 'example'
            ? $this->exampleContents($root)
            : '';

        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new EnvWriteException('Failed to create .env.');
        }
    }

    private function exampleContents(string $root): string
    {
        $example = $root.DIRECTORY_SEPARATOR.self::EXAMPLE_FILE;

        if (! is_file($example) || ! is_readable($example)) {
            throw new EnvWriteException(self::EXAMPLE_FILE.' not found or not readable in '.$root.'.');
        }

        $maxSize = (int) config('deployer.env_max_size');

        if (filesize($example) > $maxSize) {
            throw new EnvWriteException(self::EXAMPLE_FILE." is larger than {$maxSize} bytes.");
        }

        $contents = file_get_contents($example);

        if ($contents === false) {
            throw new EnvWriteException('Failed to read '.self::EXAMPLE_FILE.'.');
        }

        return $contents;
    }

    /**
     * Записывает значения в .env целевого проекта. Меняются только ключи, пришедшие в $values,
     * и только из env_visible_keys — остальной файл (порядок, комментарии, незнакомые ключи)
     * остаётся байт в байт. Ключа нет в файле — дописываем в конец; но пустое значение
     * несуществующего ключа строку-пустышку не создаёт.
     *
     * Перед записью рядом кладётся `.env.backup`: правка боевого .env из браузера должна
     * иметь путь назад, не требующий панели.
     *
     * @param  array<string, string>  $values
     * @return list<string> ключи, которые действительно изменились
     *
     * @throws PathValidationException
     * @throws EnvWriteException
     */
    public function update(Instance $instance, array $values): array
    {
        $path = $this->pathValidator->resolve($instance).DIRECTORY_SEPARATOR.'.env';

        if (! is_file($path)) {
            throw new EnvWriteException('.env not found for this instance.');
        }

        if (! is_readable($path) || ! is_writable($path)) {
            throw new EnvWriteException('The web/worker user cannot write this .env.');
        }

        $maxSize = (int) config('deployer.env_max_size');

        if (filesize($path) > $maxSize) {
            throw new EnvWriteException("The .env file is larger than {$maxSize} bytes and was not touched.");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new EnvWriteException('Failed to read .env.');
        }

        /** @var list<string> $visibleKeys */
        $visibleKeys = config('deployer.env_visible_keys', []);
        $current = $this->parse($contents);
        $changed = [];

        foreach ($values as $key => $value) {
            if (! in_array($key, $visibleKeys, true)) {
                continue;
            }

            $present = array_key_exists($key, $current);

            if ($present && $current[$key] === $value) {
                continue;
            }

            if (! $present && $value === '') {
                continue;
            }

            // Замаскированное значение браузеру не показывалось, поэтому пустое поле секрета
            // означает «не трогать», а не «стереть». Правило серверное: устаревшая форма или
            // запрос мимо интерфейса иначе затирали бы пароль пустотой.
            if ($value === '' && $this->isSensitive($key)) {
                continue;
            }

            $contents = $present
                ? $this->replaceKey($contents, $key, $value)
                : $this->appendKey($contents, $key, $value);

            $changed[] = $key;
        }

        if ($changed === []) {
            return [];
        }

        if (! @copy($path, $path.'.backup')) {
            throw new EnvWriteException('Failed to write the .env.backup copy, nothing was changed.');
        }

        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new EnvWriteException('Failed to write .env.');
        }

        return $changed;
    }

    /**
     * Хвост строки берём как [^\r\n]*, а не .*$ — иначе на CRLF-файле «конец строки» не совпадёт.
     * Значение подставляем колбэком: в строке замены `$` и `\` пришлось бы экранировать.
     */
    private function replaceKey(string $contents, string $key, string $value): string
    {
        $pattern = '/^([ \t]*(?:export[ \t]+)?'.preg_quote($key, '/').'[ \t]*=)[^\r\n]*/m';
        $formatted = $this->formatValue($value);

        return preg_replace_callback(
            $pattern,
            fn (array $matches) => $matches[1].$formatted,
            $contents,
        ) ?? $contents;
    }

    private function appendKey(string $contents, string $key, string $value): string
    {
        // Перевод строки берём тот же, что уже в файле, чтобы не смешивать CRLF и LF.
        $eol = str_contains($contents, "\r\n") ? "\r\n" : "\n";

        if ($contents !== '' && ! preg_match('/\R$/', $contents)) {
            $contents .= $eol;
        }

        return $contents.$key.'='.$this->formatValue($value).$eol;
    }

    /**
     * Кавычки ставим только там, где без них значение прочитается иначе: пробел обрежется,
     * `#` начнёт комментарий.
     */
    private function formatValue(string $value): string
    {
        if ($value !== '' && preg_match('/^[A-Za-z0-9_.\-\/:@+=]+$/', $value)) {
            return $value;
        }

        if ($value === '') {
            return '';
        }

        return '"'.str_replace(['\\', '"'], ['\\\\', '\"'], $value).'"';
    }

    /**
     * @param  array{path: string, size: int, modified_at: ?string}|null  $meta
     * @return array{status: string, message: string, file: null|array{path: string, size: int, modified_at: ?string}, variables: list<never>, hidden_count: int, example_available: bool}
     */
    private function failure(
        string $status,
        string $message,
        ?array $meta = null,
        bool $exampleAvailable = false,
    ): array {
        return [
            'status' => $status,
            'message' => $message,
            'file' => $meta,
            'variables' => [],
            'hidden_count' => 0,
            'example_available' => $exampleAvailable,
        ];
    }

    /**
     * @return array{path: string, size: int, modified_at: ?string}
     */
    private function meta(string $path): array
    {
        $modified = filemtime($path);

        return [
            'path' => $path,
            'size' => (int) filesize($path),
            'modified_at' => $modified === false ? null : Carbon::createFromTimestamp($modified)->toIso8601String(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function parse(string $contents): array
    {
        $values = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = ltrim(substr($line, 7));
            }

            if (! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = rtrim($key);

            if (! preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $key)) {
                continue;
            }

            $values[$key] = $this->normalizeValue(trim($value));
        }

        return $values;
    }

    private function normalizeValue(string $value): string
    {
        $quote = $value[0] ?? '';

        if (strlen($value) >= 2 && ($quote === '"' || $quote === "'") && str_ends_with($value, $quote)) {
            $unquoted = substr($value, 1, -1);

            // Внутри двойных кавычек \" и \\ — экранирование, а не два символа.
            return $quote === '"'
                ? str_replace(['\"', '\\\\'], ['"', '\\'], $unquoted)
                : $unquoted;
        }

        // Незакавыченное значение обрывается на inline-комментарии — как в dotenv.
        $comment = strpos($value, ' #');

        return $comment === false ? $value : rtrim(substr($value, 0, $comment));
    }

    private function isSensitive(string $key): bool
    {
        return Str::is(config('deployer.env_masked_patterns', []), $key);
    }

    private function mask(string $value): string
    {
        $length = mb_strlen($value);

        if ($length === 0) {
            return '';
        }

        if ($length <= 8) {
            return str_repeat('•', $length);
        }

        return mb_substr($value, 0, 3).str_repeat('•', 6).mb_substr($value, -3);
    }
}
