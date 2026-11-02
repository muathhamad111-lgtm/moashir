<?php

declare(strict_types=1);

namespace App\Events\Finance;

use App\Models\Finance\Refund;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class RefundRecorded implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public Refund $refund, public ?int $actorId) {}
}
