<?php

declare(strict_types=1);

namespace App\Support\Finance;

use App\Models\Finance\BankAccount;
use App\Models\Finance\BankTransfer;
use App\Models\Finance\Payment;
use App\Models\Identity\User;

final class BankTransferPresenter
{
    /**
     * @return array{payment_id: string, reference: string, entity_name: array{ar: string, en: string|null}, total: string, currency: string, submitted_at: string|null}
     */
    public static function pending(Payment $payment): array
    {
        return [
            'payment_id' => $payment->public_id,
            'reference' => $payment->reference,
            'entity_name' => $payment->entity_name->toArray(),
            'total' => $payment->total,
            'currency' => $payment->currency,
            'submitted_at' => $payment->bankTransfer?->submitted_at->toIso8601String(),
        ];
    }

    /**
     * @return array{id: string, bank_name: array{ar: string, en: string}, account_holder_name: array{ar: string, en: string}, iban: string}
     */
    public static function payeeOf(BankAccount $account): array
    {
        return [
            'id' => $account->public_id,
            'bank_name' => $account->bank_name->toArray(),
            'account_holder_name' => $account->account_holder_name->toArray(),
            'iban' => $account->iban,
        ];
    }

    /**
     * @return array{id: string, name: string, mime_type: string, size_bytes: int}
     */
    public static function proofOf(BankTransfer $transfer): array
    {
        return [
            'id' => $transfer->proofFile->public_id,
            'name' => $transfer->proofFile->original_name,
            'mime_type' => $transfer->proofFile->mime_type,
            'size_bytes' => $transfer->proofFile->size_bytes,
        ];
    }

    /**
     * @return array{id: int, name: string|null}|null
     */
    public static function reviewerOf(BankTransfer $transfer): ?array
    {
        $reviewer = $transfer->reviewedBy;

        return $reviewer instanceof User ? ['id' => $reviewer->id, 'name' => $reviewer->isSuperAdmin() ? null : $reviewer->name] : null;
    }
}
