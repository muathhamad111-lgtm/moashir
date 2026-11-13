<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum SignInIdentifierType: string
{
    case Email = 'email';
    case Mobile = 'mobile';
    case UnifiedNumber = 'unified_number';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
