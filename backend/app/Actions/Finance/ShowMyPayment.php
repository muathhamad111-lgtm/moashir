<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Contracts\Finance\OpenedRequestLookup;
use App\Data\Finance\OwnPayment;
use App\Models\Finance\Payment;

final readonly class ShowMyPayment
{
    public const array RELATIONS = ['receipt', 'bankTransfer.bankAccount', 'bankTransfer.proofFile', 'awardCycle'];

    public function __construct(private OpenedRequestLookup $openedRequests) {}

    public function handle(Payment $payment): OwnPayment
    {
        $payment->loadMissing(self::RELATIONS);

        return new OwnPayment($payment, $this->openedRequests->forPayments([$payment->id])[$payment->id] ?? null);
    }
}
