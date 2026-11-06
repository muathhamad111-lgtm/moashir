<?php

declare(strict_types=1);

namespace App\Http\Resources\Finance;

use App\Data\Finance\OpenedRequest;
use App\Data\Finance\OwnPayment;
use App\Models\Finance\BankTransfer;
use App\Support\Assessments\PathPresenter;
use App\Support\Finance\BankTransferPresenter;
use App\Support\Finance\PaymentPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read OwnPayment $resource
 */
final class OwnPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payment = $this->resource->payment;

        return [
            'id' => $payment->public_id,
            'reference' => $payment->reference,
            'purpose' => $payment->purpose->value,
            'channel' => $payment->channel->value,
            'status' => $payment->status->value,
            'is_test' => $payment->isTest(),
            'pricing' => PaymentPresenter::pricing($payment),
            'method' => $payment->method?->value,
            'card_last4' => $payment->card_last4,
            'failure_reason' => $payment->failure_reason?->value,
            'expires_at' => $payment->expires_at?->toIso8601String(),
            'created_at' => $payment->created_at->toIso8601String(),
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'failed_at' => $payment->failed_at?->toIso8601String(),
            'cancelled_at' => $payment->cancelled_at?->toIso8601String(),
            'rejected_at' => $payment->rejected_at?->toIso8601String(),
            'refunded_at' => $payment->refunded_at?->toIso8601String(),
            'receipt' => PaymentPresenter::receiptSummary($payment->receipt),
            'request' => $this->openedRequest($this->resource->openedRequest),
            'bank_transfer' => $this->bankTransferOf($payment->bankTransfer),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function bankTransferOf(?BankTransfer $transfer): ?array
    {
        if (! $transfer instanceof BankTransfer) {
            return null;
        }

        return [
            'bank_account' => BankTransferPresenter::payeeOf($transfer->bankAccount),
            'proof' => BankTransferPresenter::proofOf($transfer),
            'submitted_at' => $transfer->submitted_at->toIso8601String(),
            'reviewed_at' => $transfer->reviewed_at?->toIso8601String(),
            'rejection_reason' => $transfer->rejection_reason?->value,
            'review_note' => $transfer->review_note,
        ];
    }

    /**
     * @return array{id: int, number: string, path: string, path_label: array{ar: string, en: string}}|null
     */
    private function openedRequest(?OpenedRequest $request): ?array
    {
        return $request instanceof OpenedRequest ? ['id' => $request->id, 'number' => $request->number, ...PathPresenter::of($request->path)] : null;
    }
}
