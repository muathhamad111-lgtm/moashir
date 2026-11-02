<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Data\Integrations\ProviderPayment;
use App\Models\Finance\Payment;

final class ProviderPaymentMatcher
{
    public const string PAYMENT_ID_KEY = 'moashir_payment_id';

    public function belongsTo(Payment $payment, ProviderPayment $provider): bool
    {
        return $provider->metadataValue(self::PAYMENT_ID_KEY) === $payment->public_id;
    }

    public function amountMatches(Payment $payment, ProviderPayment $provider): bool
    {
        return $provider->amount->equals($payment->totalMoney());
    }
}
