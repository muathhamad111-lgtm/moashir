<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Finance\Payment;
use App\Support\Award\AwardCycleRefPresenter;
use App\Support\ValueObjects\VatRegistrationNumber;

final readonly class ReceiptSnapshotBuilder
{
    public const string VAT_REGISTRATION_NUMBER = 'vat_registration_number';

    public const string AWARD_CYCLE = 'award_cycle';

    public function __construct(private FinanceSettings $settings) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Payment $payment): array
    {
        $payment->loadMissing(['bankTransfer.bankAccount', 'awardCycle']);
        $vatNumber = $this->settings->vatRegistrationNumber();

        return [
            'payment_reference' => $payment->reference,
            'entity_reference' => $payment->entity->reference(),
            'entity_name' => $payment->entity_name->toArray(),
            'purpose' => $payment->purpose->value,
            ...($payment->award_cycle_id === null ? [] : [self::AWARD_CYCLE => AwardCycleRefPresenter::of($payment->awardCycle)]),
            'price_before_vat' => $payment->price_before_vat,
            'vat_rate' => $payment->vat_rate,
            'vat_amount' => $payment->vat_amount,
            'total' => $payment->total,
            'currency' => $payment->currency,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'channel' => $payment->channel->value,
            'bank_name' => $payment->bankTransfer?->bankAccount->bank_name->toArray(),
            'method' => $payment->method?->value,
            'card_last4' => $payment->card_last4,
            'provider_payment_id' => $payment->provider_payment_id,
            ...($vatNumber instanceof VatRegistrationNumber ? [self::VAT_REGISTRATION_NUMBER => $vatNumber->value] : []),
        ];
    }
}
