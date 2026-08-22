<?php

namespace App\Enums;

enum DeployStep: string
{
    case Clone = 'clone';
    case Copy = 'copy';
    case Rollback = 'rollback';
    case Backup = 'backup';
    case Restore = 'restore';
    case Git = 'git';
    case Composer = 'composer';
    case Cache = 'cache';
    case Migrate = 'migrate';
    case Frontend = 'frontend';
}
