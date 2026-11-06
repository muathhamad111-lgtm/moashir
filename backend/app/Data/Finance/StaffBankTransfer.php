<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Models\Finance\Payment;

final readonly class StaffBankTransfer
{
    /**
     * @param  list<Payment>|null  $duplicates
     */
    public function __construct(public Payment $payment, public bool $hasDuplicateProof, public ?array $duplicates = null) {}
}
