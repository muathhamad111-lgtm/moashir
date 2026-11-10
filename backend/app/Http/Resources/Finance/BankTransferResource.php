<?php

declare(strict_types=1);

namespace App\Http\Resources\Finance;

use App\Data\Finance\StaffBankTransfer;
use App\Models\Finance\BankTransfer;
use App\Models\Finance\Payment;
use App\Support\Award\AwardCycleRefPresenter;
use App\Support\Finance\PaymentPresenter;
use App\Support\Finance\StaffTransferPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read StaffBankTransfer $resource
 */
final class BankTransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payment = $this->resource->payment;
        $transfer = $payment->bankTransfer;

        return [
            'id' => $payment->public_id,
            'reference' => $payment->reference,
            'purpose' => $payment->purpose->value,
            'award_cycle' => $payment->award_cycle_id === null ? null : AwardCycleRefPresenter::of($payment->awardCycle),
            'status' => $payment->status->value,
            'is_test' => $payment->isTest(),
            'pricing' => PaymentPresenter::pricing($payment),
            'entity' => [
                'id' => $payment->entity_id,
                'reference' => $payment->entity->reference(),
                'name' => $payment->entity_name->toArray(),
                'email' => $payment->entity->owner?->email,
                'is_frozen' => $payment->entity->trashed(),
            ],
            ...($transfer instanceof BankTransfer ? StaffTransferPresenter::detailsOf($transfer) : []),
            'has_duplicate_proof' => $this->resource->hasDuplicateProof,
            'duplicate_proofs' => $this->duplicatesOf($this->resource->duplicates),
            'created_at' => $payment->created_at->toIso8601String(),
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'rejected_at' => $payment->rejected_at?->toIso8601String(),
            'receipt' => PaymentPresenter::receiptSummary($payment->receipt),
        ];
    }

    /**
     * @param  list<Payment>|null  $duplicates
     * @return list<array{payment_id: string, reference: string, status: string}>|null
     */
    private function duplicatesOf(?array $duplicates): ?array
    {
        return $duplicates === null ? null : array_map(static fn (Payment $other): array => [
            'payment_id' => $other->public_id,
            'reference' => $other->reference,
            'status' => $other->status->value,
        ], $duplicates);
    }
}
