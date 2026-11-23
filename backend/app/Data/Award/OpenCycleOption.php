<?php

declare(strict_types=1);

namespace App\Data\Award;

use App\Data\Finance\PriceQuote;
use App\Enums\Award\AwardIneligibility;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;

final readonly class OpenCycleOption
{
    public function __construct(
        public AwardCycle $cycle,
        public PriceQuote $entryFee,
        public ?AwardIneligibility $ineligibility,
        public ?Assessment $request,
    ) {}

    public function isEligible(): bool
    {
        return ! $this->ineligibility instanceof AwardIneligibility;
    }
}
