<?php

declare(strict_types=1);

namespace App\Enums\Integrations;

enum PaymentLinkedPath: string
{
    case Accredited = 'accredited';
    case Bag = 'bag';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
