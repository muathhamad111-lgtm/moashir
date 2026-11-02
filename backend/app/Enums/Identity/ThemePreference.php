<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum ThemePreference: string
{
    case Light = 'light';
    case Dark = 'dark';
    case System = 'system';
}
