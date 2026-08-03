<?php

namespace Tests\Feature;

use Tests\TestCase;

class QueueRetryAfterTest extends TestCase
{
    /**
     * Инвариант конфигурации, а не поведение кода: при retry_after меньше таймаута джобы
     * очередь отдаёт ещё идущий деплой второму воркеру, тот упирается в кэш-лок инстанса
     * и роняет живой деплой в failed. Ловим это здесь, а не на проде через неделю.
     */
    public function test_the_queue_gives_a_deployment_more_time_than_the_job_itself_takes(): void
    {
        $retryAfter = (int) config('queue.connections.database.retry_after');
        $jobTimeout = (int) config('deployer.job_timeout');

        $this->assertGreaterThan(
            $jobTimeout,
            $retryAfter,
            "DB_QUEUE_RETRY_AFTER ({$retryAfter}s) must exceed DEPLOYER_JOB_TIMEOUT ({$jobTimeout}s)."
        );
    }
}
