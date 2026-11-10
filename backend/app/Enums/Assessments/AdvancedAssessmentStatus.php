<?php

declare(strict_types=1);

namespace App\Enums\Assessments;

use App\Contracts\Assessments\AssessmentState;
use App\Contracts\Assessments\ReviewedAssessmentState;

enum AdvancedAssessmentStatus: string implements ReviewedAssessmentState
{
    case RejectedConditions = 'rejected_conditions';
    case Draft = 'draft';
    case Expired = 'expired';
    case AwaitingReviewAssignment = 'awaiting_review_assignment';
    case NeedsCompletion = 'needs_completion';
    case DeclinedIncomplete = 'declined_incomplete';
    case Withdrawn = 'withdrawn';
    case Assigned = 'assigned';
    case UnderInitialAssessment = 'under_initial_assessment';
    case AwaitingFiltering = 'awaiting_filtering';
    case NotQualified = 'not_qualified';
    case QualifiedForCommittee = 'qualified_for_committee';
    case UnderCommitteeAssessment = 'under_committee_assessment';
    case CommitteeDiscussion = 'committee_discussion';
    case AwaitingSecretaryApproval = 'awaiting_secretary_approval';
    case ReturnedToCommittee = 'returned_to_committee';
    case Approved = 'approved';
    case RegionalWinner = 'regional_winner';
    case NonWinner = 'non_winner';
    case QualifiedForNational = 'qualified_for_national';
    case NationalWinner = 'national_winner';
    case NationalNonWinner = 'national_non_winner';

    public const string ENTITY_PLACEHOLDER = 'under_review';

    private const array TERMINAL = [
        self::RejectedConditions, self::Expired, self::DeclinedIncomplete, self::Withdrawn, self::NotQualified,
        self::NonWinner, self::NationalWinner, self::NationalNonWinner,
    ];

    public function isOpen(): bool
    {
        return ! in_array($this, self::TERMINAL, true);
    }

    public function exposesResult(): bool
    {
        return in_array($this, [self::NotQualified, self::NonWinner, self::NationalWinner, self::NationalNonWinner], true);
    }

    public function allowsTransitionTo(AssessmentState $next): bool
    {
        return $next instanceof self && in_array($next, $this->nextStates(), true);
    }

    public function isInRequestQueue(): bool
    {
        return ! in_array($this, [self::RejectedConditions, self::Draft, self::Expired], true);
    }

    public function isPreparedByEntity(): bool
    {
        return $this === self::Draft;
    }

    public function runsReviewClock(): bool
    {
        return $this === self::Assigned || $this === self::UnderInitialAssessment;
    }

    public function acceptsEntityContent(): bool
    {
        return $this === self::Draft || $this === self::NeedsCompletion;
    }

    public function hasAssessor(): bool
    {
        return $this === self::Assigned || $this === self::UnderInitialAssessment;
    }

    public function isWithdrawable(): bool
    {
        return $this->isInRequestQueue() && $this->isOpen() && ! $this->isResult();
    }

    public function isQualified(): bool
    {
        return $this === self::QualifiedForCommittee || $this->isInCommittee() || $this->isResult();
    }

    public function isInCommittee(): bool
    {
        return in_array($this, self::committeeStates(), true);
    }

    public function isResult(): bool
    {
        return in_array($this, self::resultStates(), true);
    }

    public function isInFiltering(): bool
    {
        return $this === self::AwaitingFiltering || $this === self::NotQualified || $this->isQualified();
    }

    public function isHiddenFromEntityUntilAnnouncement(): bool
    {
        return $this->isInFiltering();
    }

    public function precedesSecretaryReview(): bool
    {
        return in_array($this, [
            self::AwaitingReviewAssignment, self::NeedsCompletion, self::Assigned, self::UnderInitialAssessment,
            self::AwaitingFiltering, self::QualifiedForCommittee, self::UnderCommitteeAssessment, self::ReturnedToCommittee,
        ], true);
    }

    /**
     * @return list<self>
     */
    public static function filteringStates(): array
    {
        return [self::AwaitingFiltering, self::QualifiedForCommittee, self::NotQualified];
    }

    /**
     * @return list<self>
     */
    public static function filteringVisibleStates(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $status): bool => $status->isInFiltering()));
    }

    /**
     * @return list<self>
     */
    public static function qualifiedStates(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $status): bool => $status->isQualified()));
    }

    /**
     * @return list<self>
     */
    public static function committeeStates(): array
    {
        return [self::UnderCommitteeAssessment, self::CommitteeDiscussion, self::AwaitingSecretaryApproval, self::ReturnedToCommittee];
    }

    /**
     * @return list<self>
     */
    public static function resultStates(): array
    {
        return [self::Approved, self::RegionalWinner, self::NonWinner, self::QualifiedForNational, self::NationalWinner, self::NationalNonWinner];
    }

    /**
     * @return list<self>
     */
    public static function nationalStates(): array
    {
        return [self::QualifiedForNational, self::NationalWinner, self::NationalNonWinner];
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
            self::Draft => [self::Draft, self::AwaitingReviewAssignment, self::Expired],
            self::AwaitingReviewAssignment => [self::NeedsCompletion, self::Assigned, self::Withdrawn],
            self::NeedsCompletion => [self::NeedsCompletion, self::AwaitingReviewAssignment, self::DeclinedIncomplete, self::Withdrawn],
            self::Assigned => [self::Assigned, self::UnderInitialAssessment, self::Withdrawn],
            self::UnderInitialAssessment => [self::UnderInitialAssessment, self::AwaitingFiltering, self::Withdrawn],
            self::AwaitingFiltering => [self::QualifiedForCommittee, self::NotQualified, self::Withdrawn],
            self::QualifiedForCommittee => [self::UnderCommitteeAssessment, self::Withdrawn],
            self::UnderCommitteeAssessment => [self::AwaitingSecretaryApproval, self::Withdrawn],
            self::AwaitingSecretaryApproval => [self::UnderCommitteeAssessment, self::CommitteeDiscussion, self::ReturnedToCommittee, self::Approved, self::Withdrawn],
            self::CommitteeDiscussion => [self::AwaitingSecretaryApproval, self::UnderCommitteeAssessment, self::ReturnedToCommittee, self::Withdrawn],
            self::ReturnedToCommittee => [self::UnderCommitteeAssessment, self::AwaitingSecretaryApproval, self::Withdrawn],
            self::Approved => [self::RegionalWinner, self::NonWinner],
            self::RegionalWinner => [self::QualifiedForNational],
            self::QualifiedForNational => [self::NationalWinner, self::NationalNonWinner],
            self::RejectedConditions, self::Expired, self::DeclinedIncomplete, self::Withdrawn, self::NotQualified,
            self::NonWinner, self::NationalWinner, self::NationalNonWinner => [],
        };
    }
}
