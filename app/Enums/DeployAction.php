<?php

namespace App\Enums;

enum DeployAction: string
{
    case Full = 'full';
    case Branch = 'branch';
    case Migrate = 'migrate';
    case Frontend = 'frontend';

    public function requiresBranch(): bool
    {
        return match ($this) {
            self::Full, self::Branch => true,
            self::Migrate, self::Frontend => false,
        };
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
        };
    }
}
