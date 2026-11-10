<?php

declare(strict_types=1);

namespace App\Services\Periods;

use App\Data\Periods\ResolvedPeriodState;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Periods\EntityPeriodState;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentPeriod;
use App\Models\Entities\Entity;
use Carbon\CarbonInterface;

final class PeriodStateResolver
{
    /**
     * @param  iterable<Assessment>  $requests
     */
    public function resolve(Entity $entity, AssessmentPeriod $period, iterable $requests, bool $hasEnded, int $largestReminderDays): ResolvedPeriodState
    {
        $completed = $this->firstWith($requests, static fn (AccreditedAssessmentStatus $status): bool => $status === AccreditedAssessmentStatus::Completed);

        if ($completed instanceof Assessment) {
            return new ResolvedPeriodState(EntityPeriodState::Assessed, $completed);
        }

        if ($entity->trashed()) {
            return new ResolvedPeriodState(EntityPeriodState::Frozen);
        }

        return $this->withoutResult($entity, $period, $requests, $hasEnded, $largestReminderDays);
    }

    /**
     * @param  iterable<Assessment>  $requests
     */
    private function withoutResult(Entity $entity, AssessmentPeriod $period, iterable $requests, bool $hasEnded, int $largestReminderDays): ResolvedPeriodState
    {
        $inReview = $this->firstWith($requests, static fn (AccreditedAssessmentStatus $status): bool => $status->awaitsResult());

        if ($inReview instanceof Assessment) {
            return new ResolvedPeriodState(EntityPeriodState::InProgress, $inReview);
        }

        $closed = $this->firstWith($requests, static fn (AccreditedAssessmentStatus $status): bool => $status->endedWithoutResult());

        if ($closed instanceof Assessment) {
            return new ResolvedPeriodState(EntityPeriodState::ClosedWithoutResult, $closed);
        }

        if (! $hasEnded) {
            return new ResolvedPeriodState(EntityPeriodState::NotSubmitted);
        }

        return new ResolvedPeriodState($this->joinedLate($entity, $period, $largestReminderDays) ? EntityPeriodState::NotRequired : EntityPeriodState::Missed);
    }

    private function joinedLate(Entity $entity, AssessmentPeriod $period, int $largestReminderDays): bool
    {
        $firstReminder = $period->ends_at->subDays($largestReminderDays + 1);

        return $entity->verified_at instanceof CarbonInterface && $entity->verified_at->greaterThan($firstReminder);
    }

    /**
     * @param  iterable<Assessment>  $requests
     * @param  callable(AccreditedAssessmentStatus): bool  $matches
     */
    private function firstWith(iterable $requests, callable $matches): ?Assessment
    {
        foreach ($requests as $request) {
            if ($request->status instanceof AccreditedAssessmentStatus && $matches($request->status)) {
                return $request;
            }
        }

        return null;
    }
}
