<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Finance\CheckoutIntent;
use App\Models\Finance\Payment;
use Illuminate\Support\Facades\Date;

final class CheckoutIntentTracker
{
    public function touch(Payment $payment): void
    {
        $intent = CheckoutIntent::query()
            ->where('entity_id', $payment->entity_id)
            ->where('purpose', $payment->purpose)
            ->whereNull('converted_payment_id')
            ->first() ?? new CheckoutIntent(['entity_id' => $payment->entity_id, 'purpose' => $payment->purpose]);

        $intent->last_seen_at = Date::now();
        $intent->save();
    }

    public function convert(Payment $payment): void
    {
        CheckoutIntent::query()
            ->where('entity_id', $payment->entity_id)
            ->where('purpose', $payment->purpose)
            ->whereNull('converted_payment_id')
            ->update(['converted_payment_id' => $payment->id, 'updated_at' => Date::now()]);
    }
}
