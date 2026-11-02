<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Finance\Payment;

final class PaymentRowLock
{
    public function lock(Payment $payment): Payment
    {
        return Payment::query()->with('entity')->whereKey($payment->id)->lockForUpdate()->firstOrFail();
    }
}
