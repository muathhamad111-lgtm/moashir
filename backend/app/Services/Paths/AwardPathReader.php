<?php

declare(strict_types=1);

namespace App\Services\Paths;

use App\Data\Award\AdvancedEligibility;
use App\Data\Award\EligibilityCheck;
use App\Data\Paths\AwardPathSummary;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Award\AwardIneligibility;
use App\Enums\Award\AwardWindowState;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Entities\Entity;
use App\Services\Award\AdvancedEligibilityChecker;
use App\Services\Award\CurrentCycleReader;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

final readonly class AwardPathReader
{
    public function __construct(
        private PathPricingReader $pricing,
        private CurrentCycleReader $cycles,
        private AdvancedEligibilityChecker $eligibility,
    ) {}

    public function read(Entity $entity): AwardPathSummary
    {
        $now = Date::now();
        $cycle = $this->cycles->running();
        $window = $this->windowOf($cycle, $now);
        $request = $cycle instanceof AwardCycle ? $this->requestIn($entity, $cycle) : null;

        return new AwardPathSummary(
            $this->pricing->award(),
            $window,
            $cycle,
            $window === AwardWindowState::Closed && $cycle instanceof AwardCycle ? $this->nextAfter($cycle, $now) : null,
            $this->ineligibilityOf($window, $request, $this->eligibility->check($entity, $cycle, $now)),
            $request,
        );
    }

    private function windowOf(?AwardCycle $cycle, CarbonInterface $now): AwardWindowState
    {
        return match (true) {
            ! $cycle instanceof AwardCycle => AwardWindowState::None,
            $cycle->acceptsApplicationsAt($now) => AwardWindowState::Open,
            $now->lessThan($cycle->submission_start) => AwardWindowState::Upcoming,
            default => AwardWindowState::Closed,
        };
    }

    private function ineligibilityOf(AwardWindowState $window, ?Assessment $request, AdvancedEligibility $eligibility): ?AwardIneligibility
    {
        $failure = $eligibility->failures()[0] ?? null;

        return match (true) {
            $window === AwardWindowState::None => AwardIneligibility::NoCycle,
            $window === AwardWindowState::Upcoming => AwardIneligibility::WindowNotOpen,
            $window === AwardWindowState::Closed => AwardIneligibility::WindowClosed,
            $request instanceof Assessment && $request->status !== AdvancedAssessmentStatus::RejectedConditions => AwardIneligibility::RequestExists,
            $failure instanceof EligibilityCheck => AwardIneligibility::ofRule($failure->rule),
            default => null,
        };
    }

    private function requestIn(Entity $entity, AwardCycle $cycle): ?Assessment
    {
        return Assessment::query()
            ->with(['currentResult', 'awardCycle'])
            ->ownedBy($entity->id)
            ->inCycle($cycle->id)
            ->orderByRaw('case when status = ? then 1 else 0 end', [AdvancedAssessmentStatus::RejectedConditions->value])
            ->latest('id')
            ->first();
    }

    private function nextAfter(AwardCycle $cycle, CarbonInterface $now): ?AwardCycle
    {
        return AwardCycle::query()->running()->whereKeyNot($cycle->id)->where('submission_start', '>', $now)->orderBy('submission_start')->first();
    }
}
