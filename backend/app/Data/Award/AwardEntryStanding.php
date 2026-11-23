<?php

declare(strict_types=1);

namespace App\Data\Award;

use App\Data\Paths\PathPricing;
use App\Enums\Award\AwardWindowState;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Finance\Payment;

final readonly class AwardEntryStanding
{
    public function __construct(
        public AwardWindowState $window,
        public ?AwardCycle $cycle,
        public ?Assessment $request,
        public ?Payment $awaitingPayment,
        public PathPricing $pricing,
    ) {}
}
