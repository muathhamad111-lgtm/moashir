<?php

declare(strict_types=1);

namespace App\Enums\Integrations;

enum CardNetwork: string
{
    case Mada = 'mada';
    case Visa = 'visa';
    case Mastercard = 'mastercard';
    case Amex = 'amex';
    case UnionPay = 'unionpay';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
