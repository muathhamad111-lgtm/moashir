<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Finance\StaffBankTransfer;
use App\Models\Finance\BankTransfer;
use App\Models\Finance\Payment;
use App\Services\Finance\BankTransferQuery;
use App\Services\Finance\DuplicateProofFinder;

final readonly class ShowBankTransfer
{
    public function __construct(private DuplicateProofFinder $duplicates) {}

    public function handle(Payment $payment): StaffBankTransfer
    {
        $payment->loadMissing(BankTransferQuery::RELATIONS);
        $transfer = $payment->bankTransfer;
        $duplicates = $transfer instanceof BankTransfer ? $this->duplicates->otherPaymentsWithProofOf($transfer) : [];

        return new StaffBankTransfer($payment, $duplicates !== [], $duplicates);
    }
}
