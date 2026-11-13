<?php

declare(strict_types=1);

namespace App\Enums\Settings;

enum LogoVariant: string
{
    case Light = 'light';
    case Dark = 'dark';

    public function column(): string
    {
        return $this === self::Light ? 'logo_light_file_id' : 'logo_dark_file_id';
    }
}
