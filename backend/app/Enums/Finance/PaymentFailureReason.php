<?php

declare(strict_types=1);

namespace App\Enums\Finance;

enum PaymentFailureReason: string
{
    case InsufficientFunds = 'insufficient_funds';
    case Declined = 'declined';
    case ExpiredCard = 'expired_card';
    case LostOrStolen = 'lost_or_stolen';
    case LimitExceeded = 'limit_exceeded';
    case AmountMismatch = 'amount_mismatch';
    case CheckoutUnavailable = 'checkout_unavailable';
    case SettledManually = 'settled_manually';
    case Other = 'other';

    public static function fromResponseCode(?string $responseCode): self
    {
        return match ($responseCode) {
            '51' => self::InsufficientFunds,
            '05' => self::Declined,
            '54' => self::ExpiredCard,
            '41', '43' => self::LostOrStolen,
            '61' => self::LimitExceeded,
            default => self::Other,
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
