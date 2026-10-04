<?php

namespace App\Enums;

enum DeployAction: string
{
    case Full = 'full';
    case Branch = 'branch';
    case Backup = 'backup';
    case Restore = 'restore';
    case Composer = 'composer';
    case Cache = 'cache';
    case Migrate = 'migrate';
    case Frontend = 'frontend';
    case Test = 'test';
    case Gc = 'gc';
    case Clone = 'clone';
    case Copy = 'copy';
    case Rollback = 'rollback';

    public function requiresBranch(): bool
    {
        return match ($this) {
            self::Full, self::Branch => true,
            self::Backup, self::Restore, self::Composer, self::Cache, self::Migrate, self::Frontend, self::Test, self::Gc, self::Clone, self::Copy, self::Rollback => false,
        };
    }

    /**
     * Действия, которые тестер может запустить со страницы инстанса. Clone и Copy здесь нет:
     * это разовый bootstrap рабочей копии, доступный только из админки. Restore — тоже:
     * он затирает базу стенда и идёт своим эндпоинтом, админским.
     *
     * @return list<string>
     */
    public static function userTriggerable(): array
    {
        return [
            self::Full->value,
            self::Branch->value,
            self::Backup->value,
            self::Composer->value,
            self::Cache->value,
            self::Migrate->value,
            self::Frontend->value,
            self::Test->value,
            self::Gc->value,
        ];
    }

    /**
     * @return list<DeployStep>
     */
    public function steps(): array
    {
        return match ($this) {
            // Дамп БД — первым шагом, до git: смысл его в том, что это состояние «как было
            // до деплоя», и снимать его надо, пока рабочее дерево ещё не тронуто.
            // Чистка кэшей — после composer (без vendor artisan не стартует) и строго до миграций:
            // с закэшированным конфигом migrate уедет в ту БД, что лежит в bootstrap/cache, а не в .env.
            self::Full => [DeployStep::Backup, DeployStep::Git, DeployStep::Composer, DeployStep::Cache, DeployStep::Migrate, DeployStep::Frontend],
            self::Branch => [DeployStep::Git],
            self::Backup => [DeployStep::Backup],
            self::Restore => [DeployStep::Restore],
            self::Composer => [DeployStep::Composer],
            self::Cache => [DeployStep::Cache],
            self::Migrate => [DeployStep::Migrate],
            self::Frontend => [DeployStep::Frontend],
            // Только по кнопке и никогда внутри цепочки: тесты целевого проекта ходят в базу
            // стенда, и красный прогон означал бы «деплой не удался» уже после того, как код
            // на стенде.
            self::Test => [DeployStep::Test],
            // Через очередь, а не прямым вызовом: на большом репозитории gc идёт минутами,
            // а пока он пакует объекты, git-шаг деплоя споткнулся бы о его lock-файлы.
            self::Gc => [DeployStep::Gc],
            self::Clone => [DeployStep::Clone],
            // Зависимости и фронт в копию не тащим (см. deployer.copy_excludes) — ставим заново.
            self::Copy => [DeployStep::Copy, DeployStep::Composer, DeployStep::Cache, DeployStep::Frontend],
            // Откат кода + пересборка зависимостей и фронта. Миграции не трогаем:
            // автоматический откат схемы БД слишком опасен, это делают руками.
            self::Rollback => [DeployStep::Rollback, DeployStep::Composer, DeployStep::Cache, DeployStep::Frontend],
        };
    }
}
