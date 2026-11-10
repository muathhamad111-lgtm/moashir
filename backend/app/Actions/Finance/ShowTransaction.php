<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Contracts\Finance\LinkedRequestReader;
use App\Data\Finance\StaffTransaction;
use App\Models\Finance\Payment;
use App\Models\Identity\User;

final readonly class ShowTransaction
{
    private const array RELATIONS = [
        'receipt', 'refund.payment', 'refund.recordedBy', 'refund.evidenceFile', 'entity.owner',
        'bankTransfer.bankAccount', 'bankTransfer.proofFile', 'bankTransfer.reviewedBy',
    ];

    public function __construct(private LinkedRequestReader $linkedRequests) {}

    public function handle(Payment $payment, User $viewer): StaffTransaction
    {
        $payment->loadMissing(self::RELATIONS);

        return new StaffTransaction($payment, $this->linkedRequests->forPayment($payment, $viewer));
    }
}
