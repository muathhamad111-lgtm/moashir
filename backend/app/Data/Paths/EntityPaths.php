<?php

declare(strict_types=1);

namespace App\Data\Paths;

use App\Data\Award\OpenCycleOption;
use App\Models\Award\AwardCycle;
use App\Models\Finance\Payment;

final readonly class EntityPaths
{
    /**
     * @param  list<OpenCycleOption>  $awardOpenCycles
     */
    public function __construct(
        public SelfPathSummary $self,
        public AccreditedPathSummary $accredited,
        public AwardPathSummary $award,
        public ?Payment $paymentToSettle,
        public array $awardOpenCycles,
    ) {}

    public function applicableAwardCycle(): ?AwardCycle
    {
        if ($this->award->isApplicable()) {
            return $this->award->cycle;
        }

        foreach ($this->awardOpenCycles as $option) {
            if ($option->isEligible()) {
                return $option->cycle;
            }
        }

        return null;
    }
}
