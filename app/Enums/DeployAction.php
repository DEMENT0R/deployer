<?php

namespace App\Enums;

enum DeployAction: string
{
    case Full = 'full';
    case Branch = 'branch';
    case Migrate = 'migrate';
    case Frontend = 'frontend';
    case Clone = 'clone';
    case Copy = 'copy';
    case Rollback = 'rollback';

    public function requiresBranch(): bool
    {
        return match ($this) {
            self::Full, self::Branch => true,
            self::Migrate, self::Frontend, self::Clone, self::Copy, self::Rollback => false,
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
        return [self::Full->value, self::Branch->value, self::Migrate->value, self::Frontend->value];
    }

    /**
     * @return list<DeployStep>
     */
    public function steps(): array
    {
        return match ($this) {
            self::Full => [DeployStep::Git, DeployStep::Composer, DeployStep::Migrate, DeployStep::Frontend],
            self::Branch => [DeployStep::Git],
            self::Migrate => [DeployStep::Migrate],
            self::Frontend => [DeployStep::Frontend],
            self::Clone => [DeployStep::Clone],
            // Зависимости и фронт в копию не тащим (см. deployer.copy_excludes) — ставим заново.
            self::Copy => [DeployStep::Copy, DeployStep::Composer, DeployStep::Frontend],
            // Откат кода + пересборка зависимостей и фронта. Миграции не трогаем:
            // автоматический откат схемы БД слишком опасен, это делают руками.
            self::Rollback => [DeployStep::Rollback, DeployStep::Composer, DeployStep::Frontend],
        };
    }
}
