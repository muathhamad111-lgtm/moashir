<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Models\Finance\Payment;

final readonly class OpenedOrder
{
    public function __construct(public Payment $payment, public bool $isExisting) {}
}
