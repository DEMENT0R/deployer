<?php

namespace App\Enums;

enum DeployStep: string
{
    case Git = 'git';
    case Composer = 'composer';
    case Migrate = 'migrate';
    case Frontend = 'frontend';
}
