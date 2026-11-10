<?php

declare(strict_types=1);

namespace App\Data\Paths;

use App\Data\Finance\PriceQuote;
use App\Enums\Award\AwardIneligibility;
use App\Enums\Award\AwardWindowState;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Finance\Payment;

final readonly class AwardPathSummary
{
    public function __construct(
        public PathPricing $pricing,
        public AwardWindowState $window,
        public ?AwardCycle $cycle,
        public ?AwardCycle $nextCycle,
        public ?AwardIneligibility $ineligibility,
        public ?Assessment $request,
        public ?Payment $awaitingPayment,
        public bool $isListed,
        public ?PriceQuote $cycleFee,
        public ?PriceQuote $nextCycleFee,
    ) {}

    public function isEligible(): bool
    {
        return $this->ineligibility === null;
    }

    public function isAnnounced(): bool
    {
        return $this->cycle?->isAnnounced() === true;
    }

    public function windowClosedBeforePayment(): bool
    {
        $payment = $this->request?->payment_id === null ? null : $this->request->payment;

        return $payment instanceof Payment && $this->cycle instanceof AwardCycle && $payment->wasPaidAfterWindowOf($this->cycle);
    }
}
