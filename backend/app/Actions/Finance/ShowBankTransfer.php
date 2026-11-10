<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Contracts\Finance\LinkedRequestReader;
use App\Data\Finance\StaffBankTransfer;
use App\Data\Finance\StaffBankTransferDetail;
use App\Models\Finance\BankTransfer;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Services\Finance\BankTransferQuery;
use App\Services\Finance\DuplicateProofFinder;

final readonly class ShowBankTransfer
{
    public function __construct(private DuplicateProofFinder $duplicates, private LinkedRequestReader $linkedRequests) {}

    public function handle(Payment $payment, User $viewer): StaffBankTransferDetail
    {
        $payment->loadMissing(BankTransferQuery::RELATIONS);
        $transfer = $payment->bankTransfer;
        $duplicates = $transfer instanceof BankTransfer ? $this->duplicates->otherPaymentsWithProofOf($transfer) : [];

        return new StaffBankTransferDetail(
            new StaffBankTransfer($payment, $duplicates !== [], $duplicates),
            $this->linkedRequests->forPayment($payment, $viewer),
        );
    }
}
