<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Enums\Award\CommitteeMembershipStatus;
use App\Exceptions\IllegalStateTransition;
use App\Models\Award\AwardCycle;
use App\Models\Award\CycleCommitteeAssessor;
use App\Models\Identity\User;
use App\Services\Assessments\StaffActionReason;

final readonly class CommitteeRosterGate
{
    public function __construct(
        private StaffActionReason $reason,
        private AwardCycleRowLock $rowLock,
        private CommitteeRoster $roster,
    ) {}

    public function enter(User $actor, AwardCycle $cycle, string $verb, ?string $reason): AwardCycle
    {
        $this->reason->record($actor, $reason);
        $locked = $this->rowLock->lock($cycle->id);

        if (! $locked->status->acceptsRosterChanges()) {
            throw IllegalStateTransition::from($locked->status->value, $verb);
        }

        return $locked;
    }

    public function member(CycleCommitteeAssessor $member, CommitteeMembershipStatus $expected, string $verb): CycleCommitteeAssessor
    {
        $locked = $this->roster->lock($member->id);

        if ($locked->status !== $expected) {
            throw IllegalStateTransition::from($locked->status->value, $verb);
        }

        return $locked;
    }
}
