<?php

namespace App\Enums;

enum DeployStep: string
{
    case Clone = 'clone';
    case Git = 'git';
    case Composer = 'composer';
    case Migrate = 'migrate';
    case Frontend = 'frontend';
}
