<?php

declare(strict_types=1);

namespace App\Enums\Award;

enum AwardCycleStatus: string
{
    case Draft = 'draft';
    case SubmissionOpen = 'submission_open';
    case SubmissionClosed = 'submission_closed';
    case CommitteeAssessment = 'committee_assessment';
    case AwaitingSecretary = 'awaiting_secretary';
    case Approved = 'approved';
    case Announced = 'announced';
    case Closed = 'closed';

    public function isRunning(): bool
    {
        return $this !== self::Draft && $this !== self::Closed;
    }

    public function acceptsApplications(): bool
    {
        return $this === self::SubmissionOpen;
    }

    public function acceptsDateChanges(): bool
    {
        return in_array($this, [self::Draft, self::SubmissionOpen, self::SubmissionClosed], true);
    }

    public function acceptsConditionChanges(): bool
    {
        return $this === self::Draft || $this === self::SubmissionOpen;
    }

    public function acceptsRosterChanges(): bool
    {
        return $this !== self::Closed;
    }

    public function allowsFilteringDecisions(): bool
    {
        return $this === self::SubmissionClosed || $this->isCommitteeStage();
    }

    public function isCommitteeStage(): bool
    {
        return $this === self::CommitteeAssessment || $this === self::AwaitingSecretary;
    }

    public function isClosable(): bool
    {
        return in_array($this, [self::SubmissionClosed, self::CommitteeAssessment, self::AwaitingSecretary, self::Approved, self::Announced], true);
    }

    public function allowsTransitionTo(self $next): bool
    {
        return in_array($next, $this->nextStates(), true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<self>
     */
    private function nextStates(): array
    {
        return match ($this) {
            self::Draft => [self::SubmissionOpen],
            self::SubmissionOpen => [self::SubmissionClosed],
            self::SubmissionClosed => [self::CommitteeAssessment, self::Closed],
            self::CommitteeAssessment => [self::AwaitingSecretary, self::Approved, self::Closed],
            self::AwaitingSecretary => [self::CommitteeAssessment, self::Approved, self::Closed],
            self::Approved => [self::Announced, self::Closed],
            self::Announced => [self::Closed],
            self::Closed => [],
        };
    }
}
