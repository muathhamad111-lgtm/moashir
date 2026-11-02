<?php

declare(strict_types=1);

namespace App\Enums\Finance;

enum PaymentEventResult: string
{
    case Applied = 'applied';
    case Pending = 'pending';
    case Ignored = 'ignored';
    case Rejected = 'rejected';
    case Unmatched = 'unmatched';
    case LatePayment = 'late_payment';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
