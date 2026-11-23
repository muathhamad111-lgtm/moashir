<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Data\Award\AwardEntryStanding;
use App\Data\Award\EligibilityCheck;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Award\AwardIneligibility;
use App\Enums\Award\AwardWindowState;
use App\Models\Assessments\Assessment;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use Carbon\CarbonInterface;

final readonly class AwardIneligibilityResolver
{
    public function __construct(private AdvancedEligibilityChecker $eligibility) {}

    public function resolve(Entity $entity, AwardEntryStanding $standing, CarbonInterface $now): ?AwardIneligibility
    {
        $failure = $this->eligibility->check($entity, $standing->cycle, $now)->failures()[0] ?? null;
        $request = $standing->request;

        return match (true) {
            $standing->window === AwardWindowState::None => AwardIneligibility::NoCycle,
            $standing->window === AwardWindowState::Upcoming => AwardIneligibility::WindowNotOpen,
            $standing->window === AwardWindowState::Closed => AwardIneligibility::WindowClosed,
            $request instanceof Assessment && $request->status !== AdvancedAssessmentStatus::RejectedConditions => AwardIneligibility::RequestExists,
            $standing->awaitingPayment instanceof Payment => AwardIneligibility::AwaitingPayment,
            ! $standing->pricing->isAvailable() => AwardIneligibility::PathUnavailable,
            $failure instanceof EligibilityCheck => AwardIneligibility::ofRule($failure->rule),
            default => null,
        };
    }
}
