<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Models\Finance\Payment;

final readonly class StaffTransaction
{
    public function __construct(public Payment $payment, public ?LinkedRequest $linkedRequest) {}
}
