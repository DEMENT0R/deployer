<?php

namespace App\Services;

use App\Models\Instance;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Пингует публичный URL инстанса, чтобы тестер видел, поднялся ли стенд после деплоя.
 *
 * Адрес задаёт админ в карточке инстанса, схема ограничена http/https и на записи
 * (правило валидации), и здесь — на чтении.
 */
class InstanceHealthService
{
    /**
     * @return array{status: string, message: ?string, url: ?string, code: ?int, duration_ms: ?int}
     */
    public function check(Instance $instance): array
    {
        $url = trim((string) $instance->url);

        if ($url === '') {
            return $this->result('not_configured', 'No URL is set for this instance.');
        }

        if (! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return $this->result('not_configured', 'Instance URL must be http or https.', $url);
        }

        $timeout = (int) config('deployer.health_timeout', 5);
        $startedAt = microtime(true);

        try {
            $response = Http::timeout($timeout)
                ->withHeaders(['User-Agent' => 'deployer-healthcheck'])
                ->get($url);
        } catch (ConnectionException $exception) {
            return $this->result('unreachable', trim($exception->getMessage()), $url, null, $this->elapsed($startedAt));
        } catch (Throwable $exception) {
            report($exception);

            return $this->result('unreachable', trim($exception->getMessage()) ?: 'Request failed.', $url, null, $this->elapsed($startedAt));
        }

        $code = $response->status();

        return $this->result(
            $code < 400 ? 'up' : 'down',
            $code < 400 ? null : "The instance answered with HTTP {$code}.",
            $url,
            $code,
            $this->elapsed($startedAt),
        );
    }

    /**
     * @return array{status: string, message: ?string, url: ?string, code: ?int, duration_ms: ?int}
     */
    private function result(
        string $status,
        ?string $message = null,
        ?string $url = null,
        ?int $code = null,
        ?int $durationMs = null,
    ): array {
        return [
            'status' => $status,
            'message' => $message,
            'url' => $url,
            'code' => $code,
            'duration_ms' => $durationMs,
        ];
    }

    private function elapsed(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
