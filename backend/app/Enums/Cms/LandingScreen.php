<?php

declare(strict_types=1);

namespace App\Enums\Cms;

enum LandingScreen: string
{
    case Register = 'register';
    case ChoosePath = 'choose_path';
    case Evidence = 'evidence';
    case Review = 'review';
    case Result = 'result';
    case Improvement = 'improvement';
}
