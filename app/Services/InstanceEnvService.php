<?php

namespace App\Services;

use App\Exceptions\PathValidationException;
use App\Models\Instance;
use App\Services\Deploy\PathValidator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Читает .env целевого проекта для админского просмотра.
 *
 * Наружу уходят только ключи из `deployer.env_visible_keys`, значения чувствительных
 * ключей маскируются здесь же — незамаскированное значение не должно доехать до Inertia.
 */
class InstanceEnvService
{
    public function __construct(private readonly PathValidator $pathValidator) {}

    /**
     * @return array{
     *     status: string,
     *     message: ?string,
     *     file: ?array{path: string, size: int, modified_at: ?string},
     *     variables: list<array{key: string, value: ?string, masked: bool, present: bool}>,
     *     hidden_count: int
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

        if (! is_file($path)) {
            return $this->failure('missing', ".env not found in {$root}");
        }

        if (! is_readable($path)) {
            return $this->failure('unreadable', 'The web/worker user cannot read this .env.');
        }

        $meta = $this->meta($path);
        $maxSize = (int) config('deployer.env_max_size');

        if ($meta['size'] > $maxSize) {
            return $this->failure(
                'too_large',
                "The .env file is larger than {$maxSize} bytes and was not parsed.",
                $meta
            );
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return $this->failure('unreadable', 'Failed to read .env.', $meta);
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
        ];
    }

    /**
     * @param  array{path: string, size: int, modified_at: ?string}|null  $meta
     * @return array{status: string, message: string, file: null|array{path: string, size: int, modified_at: ?string}, variables: list<never>, hidden_count: int}
     */
    private function failure(string $status, string $message, ?array $meta = null): array
    {
        return [
            'status' => $status,
            'message' => $message,
            'file' => $meta,
            'variables' => [],
            'hidden_count' => 0,
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
            return substr($value, 1, -1);
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
