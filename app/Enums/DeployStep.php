<?php

namespace App\Enums;

enum DeployStep: string
{
    case Clone = 'clone';
    case Copy = 'copy';
    case Rollback = 'rollback';
    case Git = 'git';
    case Composer = 'composer';
    case Migrate = 'migrate';
    case Frontend = 'frontend';
}
