<?php

declare(strict_types=1);

namespace App\Enums\Settings;

enum LogoVariant: string
{
    case Light = 'light';
    case Dark = 'dark';
    case Favicon = 'favicon';
    case Site = 'site';

    public function column(): string
    {
        return match ($this) {
            self::Light => 'logo_light_file_id',
            self::Dark => 'logo_dark_file_id',
            self::Favicon => 'favicon_file_id',
            self::Site => 'site_logo_file_id',
        };
    }

    /**
     * @return list<string>
     */
    public static function columns(): array
    {
        return array_map(static fn (self $variant): string => $variant->column(), self::cases());
    }
}
