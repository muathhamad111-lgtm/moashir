<?php

declare(strict_types=1);

namespace App\Enums\Settings;

/**
 * The colours the platform derives from the two base brand colours. Each one may be
 * overridden by the client, who is responsible for the readability of the colour they choose.
 */
enum DerivedBrandColor: string
{
    case OnPrimary = 'on_primary';
    case PrimaryText = 'primary_text';
    case PrimaryHover = 'primary_hover';
    case PrimarySoft = 'primary_soft';
    case OnSecondary = 'on_secondary';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_map(static fn (self $color): string => $color->value, self::cases());
    }
}
