<?php

namespace App\Services;

use App\Exceptions\CacheClearException;
use App\Exceptions\PathValidationException;
use App\Models\Instance;
use App\Services\Deploy\PathValidator;
use App\Services\Deploy\ProcessRunner;

/**
 * Сброс кэшей целевого проекта командой инстанса (`cache_command`) вне деплоя.
 *
 * Нужен там, где панель поменяла `.env` инстанса: с закэшированным конфигом стенд продолжает
 * жить со старой БД, и правка из панели выглядит как не применившаяся. В деплое ту же команду
 * выполняет шаг cache — здесь она гоняется синхронно, поэтому таймаут короткий: запрос ждёт
 * открытая страница, а чистка кэшей — секундное дело.
 */
class InstanceCacheService
{
    private const TIMEOUT = 60;

    public function __construct(
        private readonly ProcessRunner $runner,
        private readonly PathValidator $pathValidator,
    ) {}

    /**
     * @return string Вывод команды.
     *
     * @throws CacheClearException
     * @throws PathValidationException
     */
    public function clear(Instance $instance): string
    {
        $command = $instance->cache_command;

        if (blank($command)) {
            throw new CacheClearException('No cache command is set for this instance.');
        }

        $cwd = $this->pathValidator->resolve($instance);

        $result = $this->runner->runShell($command, $cwd, null, self::TIMEOUT);

        if (! $result->successful) {
            throw new CacheClearException($result->combinedOutput() ?: 'Failed to clear caches.');
        }

        return trim($result->combinedOutput());
    }
}
