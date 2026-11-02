<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Enums\Finance\PaymentEventResult;
use App\Models\Finance\Payment;

final readonly class VerificationOutcome
{
    public function __construct(public Payment $payment, public PaymentEventResult $result) {}

    public function isPending(): bool
    {
        return $this->result === PaymentEventResult::Pending;
    }
}
