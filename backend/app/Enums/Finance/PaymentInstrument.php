<?php

declare(strict_types=1);

namespace App\Enums\Finance;

enum PaymentInstrument: string
{
    case CreditCard = 'creditcard';
    case Mada = 'mada';
    case ApplePay = 'applepay';
    case StcPay = 'stcpay';

    private const string MADA_COMPANY = 'mada';

    public static function fromSource(?string $sourceType, ?string $company): ?self
    {
        return match ($sourceType) {
            'creditcard' => $company !== null && mb_strtolower($company) === self::MADA_COMPANY ? self::Mada : self::CreditCard,
            'applepay' => self::ApplePay,
            'stcpay' => self::StcPay,
            default => null,
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
