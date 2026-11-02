<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Data\Integrations\VerifiedPaymentEvent;
use App\Models\Finance\Payment;
use Illuminate\Support\Str;

final class WebhookPaymentMatcher
{
    public function match(VerifiedPaymentEvent $event): ?Payment
    {
        $byPublicId = $event->moashirPaymentId !== null && Str::isUuid($event->moashirPaymentId)
            ? Payment::query()->where('public_id', $event->moashirPaymentId)->first()
            : null;

        if ($byPublicId instanceof Payment) {
            return $byPublicId;
        }

        return $event->providerPaymentId === null ? null : Payment::query()->where('provider_payment_id', $event->providerPaymentId)->first();
    }
}
