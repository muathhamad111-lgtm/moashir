<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Data\Audit\AuditEntry;
use App\Data\Finance\OpenedOrder;
use App\Data\Finance\PriceQuote;
use App\Enums\Audit\AuditEvent;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Integrations\IntegrationMode;
use App\Events\Finance\PaymentCreated;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
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

    public function open(User $actor, Entity $entity, PaymentPurpose $purpose, PriceQuote $quote, IntegrationMode $mode, string $idempotencyKey): OpenedOrder
    {
        Entity::query()->whereKey($entity->id)->lockForUpdate()->first();

        $existing = Payment::query()->ownedBy($entity->id)->where('purpose', $purpose)->open()->first();

        if ($existing instanceof Payment) {
            return new OpenedOrder($existing, true);
        }

        $this->eligibility->assertMayPurchase($entity, $purpose);

        $payment = Payment::query()->create([
            'reference' => $this->references->next(),
            'entity_id' => $entity->id,
            'entity_name' => $entity->name,
            'initiated_by_user_id' => $actor->id,
            'purpose' => $purpose,
            'mode' => $mode,
            'idempotency_key' => $idempotencyKey,
            'price_before_vat' => $quote->priceBeforeVat->amount,
            'vat_rate' => $quote->vatRate->percent,
            'vat_amount' => $quote->vatAmount->amount,
            'total' => $quote->total->amount,
            'currency' => $quote->total->currency,
            'status' => PaymentStatus::Created,
            'expires_at' => Date::now()->addMinutes($this->settings->orderValidityMinutes()),
        ]);
        $payment->setRelation('entity', $entity);

        $this->intents->touch($payment);
        $this->audit->record(new AuditEntry(AuditEvent::PaymentCreated, $payment, [], [
            'reference' => $payment->reference,
            'purpose' => $purpose->value,
            'mode' => $mode->value,
            'price_before_vat' => $payment->price_before_vat,
            'vat_rate' => $payment->vat_rate,
            'vat_amount' => $payment->vat_amount,
            'total' => $payment->total,
            'expires_at' => $payment->expires_at->toIso8601String(),
        ]));

        PaymentCreated::dispatch($payment);

        return new OpenedOrder($payment, false);
    }
}
