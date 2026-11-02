<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Data\Audit\AuditEntry;
use App\Data\Integrations\ProviderPayment;
use App\Enums\Audit\AuditEvent;
use App\Enums\Finance\PaymentInstrument;
use App\Models\Finance\Payment;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\Date;

final readonly class LatePaymentRecorder
{
    public function __construct(private AuditRecorder $audit) {}

    public function record(Payment $cancelled, ProviderPayment $provider): bool
    {
        if ($cancelled->late_paid_at !== null) {
            return false;
        }

        $cancelled->forceFill([
            'provider_payment_id' => $cancelled->provider_payment_id ?? $provider->id,
            'bound_at' => $cancelled->bound_at ?? Date::now(),
            'late_paid_at' => Date::now(),
            'method' => PaymentInstrument::fromSource($provider->sourceType, $provider->cardCompany),
            'card_last4' => $provider->cardLast4,
        ])->save();

        $this->audit->record(new AuditEntry(AuditEvent::PaymentLatePaid, $cancelled, [], [
            'status' => $cancelled->status->value,
            'provider_payment_id' => $provider->id,
            'provider_amount' => $provider->amount->amount,
            'provider_currency' => $provider->amount->currency,
        ]));

        return true;
    }
}
