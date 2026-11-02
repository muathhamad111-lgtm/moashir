<?php

declare(strict_types=1);

namespace App\Enums\Assessments;

use App\Contracts\Assessments\AssessmentState;
use App\Contracts\Assessments\ReviewedAssessmentState;

enum AccreditedAssessmentStatus: string implements ReviewedAssessmentState
{
    case AwaitingPayment = 'awaiting_payment';
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Assigned = 'assigned';
    case UnderReview = 'under_review';
    case NeedsCompletion = 'needs_completion';
    case Completed = 'completed';
    case Refunded = 'refunded';
    case DeclinedIncomplete = 'declined_incomplete';

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Completed, self::Refunded, self::DeclinedIncomplete], true);
    }

    public function exposesResult(): bool
    {
        return $this === self::Completed;
    }

    public function allowsTransitionTo(AssessmentState $next): bool
    {
        return $next instanceof self && in_array($next, $this->nextStates(), true);
    }

    public function isInRequestQueue(): bool
    {
        return ! in_array($this, [self::AwaitingPayment, self::Draft], true);
    }

    public function runsReviewClock(): bool
    {
        return in_array($this, [self::Submitted, self::Assigned, self::UnderReview], true);
    }

    public function acceptsEntityContent(): bool
    {
        return $this === self::Draft || $this === self::NeedsCompletion;
    }

    public function hasAssessor(): bool
    {
        return in_array($this, [self::Assigned, self::UnderReview, self::NeedsCompletion], true);
    }

    public function isForceTarget(): bool
    {
        return in_array($this, [self::Draft, self::Submitted, self::Assigned, self::UnderReview, self::Completed], true);
    }

    /**
     * @return list<string>
     */
    public static function forceTargetValues(): array
    {
        return self::valuesOf(...array_filter(self::cases(), static fn (self $status): bool => $status->isForceTarget()));
    }

    /**
     * @return list<self>
     */
    public static function queueStates(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $status): bool => $status->isInRequestQueue()));
    }

    /**
     * @return list<string>
     */
    public static function valuesOf(self ...$states): array
    {
        return array_values(array_map(static fn (self $state): string => $state->value, $states));
    }

    /**
     * @return list<self>
     */
    private function nextStates(): array
    {
        return match ($this) {
            self::AwaitingPayment => [self::Draft],
            self::Draft => [self::Draft, self::Submitted, self::Refunded],
            self::Submitted => [self::Assigned, self::Refunded],
            self::Assigned => [self::Assigned, self::UnderReview, self::Refunded],
            self::UnderReview => [self::UnderReview, self::NeedsCompletion, self::Completed, self::Refunded],
            self::NeedsCompletion => [self::NeedsCompletion, self::UnderReview, self::DeclinedIncomplete, self::Refunded],
            self::Completed, self::Refunded, self::DeclinedIncomplete => [],
        };
    }
}
