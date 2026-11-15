<?php

declare(strict_types=1);

namespace App\Support\Finance;

use App\Models\Files\StoredFile;
use App\Models\Finance\Refund;

final class RefundPresenter
{
    /**
     * The refund as a ledger row shows it. Expects `recordedBy` and `evidenceFile` eager loaded; the
     * evidence itself is fetched through `POST finance/refunds/{id}/evidence/download`.
     *
     * @return array{id: string, amount: string, currency: string, refunded_on: string, transfer_reference: string, recorded_by: array{id: int, name: string}, has_evidence: bool}
     */
    public static function summaryOf(Refund $refund): array
    {
        return [
            'id' => $refund->public_id,
            'amount' => $refund->amount,
            'currency' => $refund->currency,
            'refunded_on' => $refund->refunded_on->toDateString(),
            'transfer_reference' => $refund->transfer_reference,
            'recorded_by' => ['id' => $refund->recorded_by_user_id, 'name' => $refund->recordedBy->name],
            'has_evidence' => $refund->getRelationValue('evidenceFile') instanceof StoredFile,
        ];
    }
}
