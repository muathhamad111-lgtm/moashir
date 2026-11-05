<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Contracts\Finance\OpenedRequestLookup;
use App\Data\Finance\OwnPayment;
use App\Models\Finance\Payment;

final readonly class ShowMyPayment
{
    public function __construct(private OpenedRequestLookup $openedRequests) {}

    public function handle(Payment $payment): OwnPayment
    {
        return new OwnPayment($payment, $this->openedRequests->forPayments([$payment->id])[$payment->id] ?? null);
    }
}
