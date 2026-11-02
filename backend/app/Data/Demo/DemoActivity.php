<?php

declare(strict_types=1);

namespace App\Data\Demo;

use App\Models\Award\AwardCycle;
use App\Models\Finance\Payment;

final readonly class DemoActivity
{
    /**
     * @param  list<Payment>  $payments
     */
    public function __construct(public AwardCycle $cycle, public array $payments) {}
}
