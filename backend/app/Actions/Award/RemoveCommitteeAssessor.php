<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\CommitteeAssignmentStatus;
use App\Enums\Award\CommitteeMembershipStatus;
use App\Enums\Award\CommitteeVoidReason;
use App\Exceptions\Award\CommitteeRejected;
use App\Models\Award\AwardCycle;
use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CommitteeScore;
use App\Models\Award\CycleCommitteeAssessor;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Award\CommitteeAssignmentVoider;
use App\Services\Award\CommitteeCycleDistributor;
use App\Services\Award\CommitteeRoster;
use App\Services\Award\CommitteeRosterGate;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class RemoveCommitteeAssessor
{
    private const string VERB = 'remove_committee_assessor';

    public function __construct(
        private CommitteeRosterGate $gate,
        private CommitteeRoster $roster,
        private CommitteeAssignmentVoider $voider,
        private CommitteeCycleDistributor $distributor,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, AwardCycle $cycle, CycleCommitteeAssessor $member, ?string $reason): void
    {
        DB::transaction(function () use ($actor, $cycle, $member, $reason): void {
            $locked = $this->gate->enter($actor, $cycle, self::VERB, $reason);
            $seat = $this->gate->member($member, CommitteeMembershipStatus::Active, self::VERB);

            if ($this->hasWork($seat)) {
                throw CommitteeRejected::hasScores();
            }

            if ($this->distributor->hasCommitteeRequests($locked) && ! $this->roster->hasOtherActive($locked->id, $seat->id)) {
                throw CommitteeRejected::noneActive();
            }

            $seat->forceFill(['status' => CommitteeMembershipStatus::Removed, 'removed_at' => Date::now()])->save();
            $affected = $this->voider->void(CommitteeAssignment::query()->where('cycle_committee_assessor_id', $seat->id), CommitteeVoidReason::Removed);
            $this->distributor->resync($locked, $affected, $actor->id);
            $this->audit->record(new AuditEntry(AuditEvent::CommitteeAssessorRemoved, $locked, ['member_id' => $seat->id, 'status' => CommitteeMembershipStatus::Active->value], [
                'status' => CommitteeMembershipStatus::Removed->value,
                'assessor_user_id' => $seat->assessor_user_id,
                'voided_requests' => $affected,
            ]));
        });
    }

    private function hasWork(CycleCommitteeAssessor $seat): bool
    {
        $assignments = CommitteeAssignment::query()->where('cycle_committee_assessor_id', $seat->id);

        return (clone $assignments)->whereIn('status', [CommitteeAssignmentStatus::InProgress, CommitteeAssignmentStatus::Completed])->exists()
            || CommitteeScore::query()->whereIn('committee_assignment_id', $assignments->select('id'))->exists();
    }
}
