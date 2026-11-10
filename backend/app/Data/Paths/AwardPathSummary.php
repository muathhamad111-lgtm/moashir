<?php

declare(strict_types=1);

namespace App\Data\Paths;

use App\Enums\Award\AwardIneligibility;
use App\Enums\Award\AwardWindowState;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;

final readonly class AwardPathSummary
{
    public function __construct(
        public PathPricing $pricing,
        public AwardWindowState $window,
        public ?AwardCycle $cycle,
        public ?AwardCycle $nextCycle,
        public ?AwardIneligibility $ineligibility,
        public ?Assessment $request,
    ) {}

    public function isEligible(): bool
    {
        return $this->ineligibility === null;
    }

    public function isAnnounced(): bool
    {
        return $this->cycle?->isAnnounced() === true;
    }
}
