<?php

declare(strict_types=1);

namespace App\Enums\Integrations;

enum PaymentMethod: string
{
    case CreditCard = 'creditcard';
    case ApplePay = 'applepay';
    case StcPay = 'stcpay';

    public function requiredConfirmation(): ?PaymentConfirmation
    {
        return match ($this) {
            self::CreditCard => null,
            self::ApplePay => PaymentConfirmation::ApplePayDomain,
            self::StcPay => PaymentConfirmation::StcPayActivation,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
