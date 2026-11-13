<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Data\Audit\AuditEntry;
use App\Data\Finance\OrderTerms;
use App\Enums\Audit\AuditEvent;
use App\Enums\Finance\PaymentChannel;
use App\Events\Finance\PaymentCreated;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

final readonly class PaymentOrderWriter
{
    public function __construct(
        private PaymentReferenceAllocator $references,
        private FinanceSettings $settings,
        private CheckoutIntentTracker $intents,
        private AuditRecorder $audit,
    ) {}

    public function write(User $actor, Entity $entity, OrderTerms $terms): Payment
    {
        $payment = Payment::query()->create([
            'reference' => $this->references->next(),
            'entity_id' => $entity->id,
            'entity_name' => $entity->name,
            'initiated_by_user_id' => $actor->id,
            'purpose' => $terms->purpose,
            'award_cycle_id' => $terms->cycle?->id,
            'channel' => $terms->channel,
            'provider' => $terms->channel->providerName(),
            'mode' => $terms->mode,
            'idempotency_key' => $terms->idempotencyKey,
            'price_before_vat' => $terms->quote->priceBeforeVat->amount,
            'vat_rate' => $terms->quote->vatRate->percent,
            'vat_amount' => $terms->quote->vatAmount->amount,
            'total' => $terms->quote->total->amount,
            'currency' => $terms->quote->total->currency,
            'status' => $terms->channel->initialStatus(),
            'expires_at' => $this->expiryOf($terms->channel),
        ]);
        $payment->setRelation('entity', $entity);

        if ($terms->purpose->tracksCheckoutIntent()) {
            $this->intents->touch($payment);
        }
        $this->recordCreation($payment);

        if ($terms->channel === PaymentChannel::Electronic) {
            PaymentCreated::dispatch($payment);
        }

        return $payment;
    }

    private function expiryOf(PaymentChannel $channel): ?CarbonInterface
    {
        return $channel === PaymentChannel::Electronic ? Date::now()->addMinutes($this->settings->orderValidityMinutes()) : null;
    }

    private function recordCreation(Payment $payment): void
    {
        $this->audit->record(new AuditEntry(AuditEvent::PaymentCreated, $payment, [], [
            'reference' => $payment->reference,
            'purpose' => $payment->purpose->value,
            'award_cycle_id' => $payment->award_cycle_id,
            'channel' => $payment->channel->value,
            'mode' => $payment->mode->value,
            'price_before_vat' => $payment->price_before_vat,
            'vat_rate' => $payment->vat_rate,
            'vat_amount' => $payment->vat_amount,
            'total' => $payment->total,
            'expires_at' => $payment->expires_at?->toIso8601String(),
        ]));
    }
}
