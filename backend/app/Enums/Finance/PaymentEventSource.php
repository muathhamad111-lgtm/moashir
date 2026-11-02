<?php

declare(strict_types=1);

namespace App\Enums\Finance;

enum PaymentEventSource: string
{
    case Checkout = 'checkout';
    case Verification = 'verification';
    case Webhook = 'webhook';
    case Expiry = 'expiry';
    case Reconciliation = 'reconciliation';
    case Admin = 'admin';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
