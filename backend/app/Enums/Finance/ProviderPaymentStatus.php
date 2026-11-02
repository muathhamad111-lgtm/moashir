<?php

declare(strict_types=1);

namespace App\Enums\Finance;

enum ProviderPaymentStatus: string
{
    case Initiated = 'initiated';
    case Paid = 'paid';
    case Failed = 'failed';
    case Authorized = 'authorized';
    case Captured = 'captured';
    case Verified = 'verified';
    case Refunded = 'refunded';
    case Voided = 'voided';
    case Expired = 'expired';

    public static function effectOf(string $status): ProviderStatusEffect
    {
        return self::tryFrom($status)?->effect() ?? ProviderStatusEffect::Unexpected;
    }

    public function effect(): ProviderStatusEffect
    {
        return match ($this) {
            self::Initiated => ProviderStatusEffect::AwaitPayment,
            self::Paid => ProviderStatusEffect::Settle,
            self::Failed => ProviderStatusEffect::Fail,
            self::Refunded, self::Voided => ProviderStatusEffect::ReportRefund,
            self::Authorized, self::Captured, self::Verified, self::Expired => ProviderStatusEffect::Unexpected,
        };
    }
}
