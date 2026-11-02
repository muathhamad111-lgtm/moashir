<?php

declare(strict_types=1);

namespace App\Events\Finance;

use App\Models\Finance\PaymentReceipt;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class ReceiptIssued implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public PaymentReceipt $receipt) {}
}
