<?php

declare(strict_types=1);

namespace App\Events\Finance;

use App\Enums\Finance\CancellationCause;
use App\Models\Finance\Payment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class PaymentCancelled implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public Payment $payment, public CancellationCause $cause) {}
}
