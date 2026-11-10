<?php

declare(strict_types=1);

namespace App\Services\Paths;

use App\Data\Award\AdvancedEligibility;
use App\Data\Award\EligibilityCheck;
use App\Data\Paths\AwardPathSummary;
use App\Data\Paths\PathPricing;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Award\AwardIneligibility;
use App\Enums\Award\AwardWindowState;
use App\Enums\Finance\PaymentPurpose;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use App\Services\Award\AdvancedEligibilityChecker;
use App\Services\Award\AwardEntryLedger;
use App\Services\Award\AwardWindowReader;
use Illuminate\Support\Facades\Date;

final readonly class AwardPathReader
{
    public function __construct(
        private PathPricingReader $pricing,
        private AwardWindowReader $windows,
        private AdvancedEligibilityChecker $eligibility,
        private AwardEntryLedger $ledger,
        private PathListing $listing,
    ) {}

    public function read(Entity $entity): AwardPathSummary
    {
        $now = Date::now();
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
            $this->ineligibilityOf($window, $request, $awaitingPayment, $pricing, $this->eligibility->check($entity, $cycle, $now)),
            $request,
            $awaitingPayment,
            $this->listing->isListed($entity, PaymentPurpose::Award, $pricing),
            $cycle instanceof AwardCycle ? $this->pricing->quoteOf($cycle->entryFee()) : null,
            $nextCycle instanceof AwardCycle ? $this->pricing->quoteOf($nextCycle->entryFee()) : null,
        );
    }

    private function ineligibilityOf(AwardWindowState $window, ?Assessment $request, ?Payment $awaitingPayment, PathPricing $pricing, AdvancedEligibility $eligibility): ?AwardIneligibility
    {
        $failure = $eligibility->failures()[0] ?? null;

        return match (true) {
            $window === AwardWindowState::None => AwardIneligibility::NoCycle,
            $window === AwardWindowState::Upcoming => AwardIneligibility::WindowNotOpen,
            $window === AwardWindowState::Closed => AwardIneligibility::WindowClosed,
            $request instanceof Assessment && $request->status !== AdvancedAssessmentStatus::RejectedConditions => AwardIneligibility::RequestExists,
            $awaitingPayment instanceof Payment => AwardIneligibility::AwaitingPayment,
            ! $pricing->isAvailable() => AwardIneligibility::PathUnavailable,
            $failure instanceof EligibilityCheck => AwardIneligibility::ofRule($failure->rule),
            default => null,
        };
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
