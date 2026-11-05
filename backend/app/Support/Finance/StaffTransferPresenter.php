<?php

declare(strict_types=1);

namespace App\Support\Finance;

use App\Models\Finance\BankTransfer;

final class StaffTransferPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function detailsOf(BankTransfer $transfer): array
    {
        $account = $transfer->bankAccount;

        return [
            'bank_account' => [
                ...BankTransferPresenter::payeeOf($account),
                'is_active' => $account->is_active,
                'is_archived' => $account->archived_at !== null,
            ],
            'proof' => [...BankTransferPresenter::proofOf($transfer), 'scan_status' => $transfer->proofFile->malware_scan_status->value],
            'submitted_at' => $transfer->submitted_at->toIso8601String(),
            'reviewed_at' => $transfer->reviewed_at?->toIso8601String(),
            'reviewed_by' => BankTransferPresenter::reviewerOf($transfer),
            'rejection_reason' => $transfer->rejection_reason?->value,
            'review_note' => $transfer->review_note,
        ];
    }
}
