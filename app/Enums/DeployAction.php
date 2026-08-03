<?php

namespace App\Enums;

enum DeployAction: string
{
    case Full = 'full';
    case Branch = 'branch';
    case Composer = 'composer';
    case Cache = 'cache';
    case Migrate = 'migrate';
    case Frontend = 'frontend';
    case Clone = 'clone';
    case Copy = 'copy';
    case Rollback = 'rollback';

    public function requiresBranch(): bool
    {
        return match ($this) {
            self::Full, self::Branch => true,
            self::Composer, self::Cache, self::Migrate, self::Frontend, self::Clone, self::Copy, self::Rollback => false,
        };
    }

    /**
     * Действия, которые тестер может запустить со страницы инстанса. Clone и Copy здесь нет:
     * это разовый bootstrap рабочей копии, доступный только из админки.
     *
     * @return list<string>
     */
    public static function userTriggerable(): array
    {
        return [
            self::Full->value,
            self::Branch->value,
            self::Composer->value,
            self::Cache->value,
            self::Migrate->value,
            self::Frontend->value,
        ];
    }

    /**
     * @return list<DeployStep>
     */
    public function steps(): array
    {
        return match ($this) {
            // Чистка кэшей — после composer (без vendor artisan не стартует) и строго до миграций:
            // с закэшированным конфигом migrate уедет в ту БД, что лежит в bootstrap/cache, а не в .env.
            self::Full => [DeployStep::Git, DeployStep::Composer, DeployStep::Cache, DeployStep::Migrate, DeployStep::Frontend],
            self::Branch => [DeployStep::Git],
            self::Composer => [DeployStep::Composer],
            self::Cache => [DeployStep::Cache],
            self::Migrate => [DeployStep::Migrate],
            self::Frontend => [DeployStep::Frontend],
            self::Clone => [DeployStep::Clone],
            // Зависимости и фронт в копию не тащим (см. deployer.copy_excludes) — ставим заново.
            self::Copy => [DeployStep::Copy, DeployStep::Composer, DeployStep::Cache, DeployStep::Frontend],
            // Откат кода + пересборка зависимостей и фронта. Миграции не трогаем:
            // автоматический откат схемы БД слишком опасен, это делают руками.
            self::Rollback => [DeployStep::Rollback, DeployStep::Composer, DeployStep::Cache, DeployStep::Frontend],
        };
    }
}
