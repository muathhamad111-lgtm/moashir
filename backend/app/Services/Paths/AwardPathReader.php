<?php

declare(strict_types=1);

namespace App\Services\Paths;

use App\Data\Award\AwardEntryStanding;
use App\Data\Paths\AwardPathSummary;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Finance\PaymentPurpose;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Entities\Entity;
use App\Services\Award\AwardEntryLedger;
use App\Services\Award\AwardIneligibilityResolver;
use App\Services\Award\AwardWindowReader;
use Illuminate\Support\Facades\Date;

final readonly class AwardPathReader
{
    public function __construct(
        private PathPricingReader $pricing,
        private AwardWindowReader $windows,
        private AwardEntryLedger $ledger,
        private PathListing $listing,
        private AwardIneligibilityResolver $ineligibility,
    ) {}

    public function read(Entity $entity): AwardPathSummary
    {
        $awardWindow = $this->windows->read();
        $cycle = $awardWindow->cycle;
        $window = $awardWindow->state;
        $nextCycle = $awardWindow->nextCycle;
        $request = $cycle instanceof AwardCycle ? $this->requestIn($entity, $cycle) : null;
        $pricing = $this->pricing->award($awardWindow->feeCycle());
        $awaitingPayment = $cycle instanceof AwardCycle ? $this->ledger->openOrder($entity->id, $cycle->id) : null;

        return new AwardPathSummary(
            $pricing,
            $window,
            $cycle,
            $nextCycle,
            $this->ineligibility->resolve($entity, new AwardEntryStanding($window, $cycle, $request, $awaitingPayment, $pricing), Date::now()),
            $request,
            $awaitingPayment,
            $this->listing->isListed($entity, PaymentPurpose::Award, $pricing),
            $cycle instanceof AwardCycle ? $this->pricing->quoteOf($cycle->entryFee()) : null,
            $nextCycle instanceof AwardCycle ? $this->pricing->quoteOf($nextCycle->entryFee()) : null,
        );
    }

    private function requestIn(Entity $entity, AwardCycle $cycle): ?Assessment
    {
        return Assessment::query()
            ->with(['currentResult', 'awardCycle', 'payment.receipt'])
            ->ownedBy($entity->id)
            ->inCycle($cycle->id)
            ->orderByRaw('case when status = ? then 1 else 0 end', [AdvancedAssessmentStatus::RejectedConditions->value])
            ->latest('id')
            ->first();
    }
}
