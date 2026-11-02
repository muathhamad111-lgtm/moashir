<?php

declare(strict_types=1);

namespace App\Enums\Files;

enum FileVisibility: string
{
    case Private = 'private';
    case PublicAsset = 'public_asset';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
