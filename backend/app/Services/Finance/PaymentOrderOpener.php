<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Data\Audit\AuditEntry;
use App\Data\Finance\OpenedOrder;
use App\Data\Finance\OrderTerms;
use App\Enums\Audit\AuditEvent;
use App\Enums\Finance\PaymentChannel;
use App\Events\Finance\PaymentCreated;
use App\Exceptions\Finance\PaymentRejected;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

final readonly class PaymentOrderOpener
{
    public function __construct(
        private PaymentReferenceAllocator $references,
        private FinanceSettings $settings,
        private CheckoutIntentTracker $intents,
        private PurchaseEligibility $eligibility,
        private AuditRecorder $audit,
    ) {}

    public function open(User $actor, Entity $entity, OrderTerms $terms): OpenedOrder
    {
        Entity::query()->whereKey($entity->id)->lockForUpdate()->first();

        $existing = Payment::query()->ownedBy($entity->id)->where('purpose', $terms->purpose)->open()->first();

        if ($existing instanceof Payment) {
            return $this->reuse($existing, $terms);
        }

        $this->eligibility->assertMayPurchase($entity, $terms->purpose);

        $payment = Payment::query()->create([
            'reference' => $this->references->next(),
            'entity_id' => $entity->id,
            'entity_name' => $entity->name,
            'initiated_by_user_id' => $actor->id,
            'purpose' => $terms->purpose,
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

        $this->intents->touch($payment);
        $this->recordCreation($payment);

        if ($terms->channel === PaymentChannel::Electronic) {
            PaymentCreated::dispatch($payment);
        }

        return new OpenedOrder($payment, false);
    }

    private function reuse(Payment $existing, OrderTerms $terms): OpenedOrder
    {
        if ($terms->channel !== PaymentChannel::Electronic || $existing->channel !== PaymentChannel::Electronic) {
            throw PaymentRejected::openOrderExists($existing);
        }

        return new OpenedOrder($existing, true);
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
