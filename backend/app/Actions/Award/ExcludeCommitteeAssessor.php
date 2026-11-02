<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Audit\AuditEntry;
use App\Data\Award\RosterChangeOutcome;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\CommitteeMembershipStatus;
use App\Enums\Award\CommitteeVoidReason;
use App\Exceptions\Award\CommitteeRejected;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CycleCommitteeAssessor;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Award\CommitteeAssignmentVoider;
use App\Services\Award\CommitteeCycleDistributor;
use App\Services\Award\CommitteeRoster;
use App\Services\Award\CommitteeRosterGate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final readonly class ExcludeCommitteeAssessor
{
    private const string VERB = 'exclude_committee_assessor';

    public function __construct(
        private CommitteeRosterGate $gate,
        private CommitteeRoster $roster,
        private CommitteeAssignmentVoider $voider,
        private CommitteeCycleDistributor $distributor,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, AwardCycle $cycle, CycleCommitteeAssessor $member, ?string $reason): RosterChangeOutcome
    {
        return DB::transaction(function () use ($actor, $cycle, $member, $reason): RosterChangeOutcome {
            $locked = $this->gate->enter($actor, $cycle, self::VERB, $reason);
            $seat = $this->gate->member($member, CommitteeMembershipStatus::Disabled, self::VERB);

            if ($this->distributor->hasCommitteeRequests($locked) && ! $this->roster->hasOtherActive($locked->id, $seat->id)) {
                throw CommitteeRejected::noneActive();
            }

            $seat->forceFill(['status' => CommitteeMembershipStatus::Excluded])->save();
            $affected = $this->voider->void($this->openRowsOf($seat), CommitteeVoidReason::Excluded);
            $recomputed = $this->distributor->resync($locked, $affected, $actor->id);
            $this->audit->record(new AuditEntry(AuditEvent::CommitteeAssessorExcluded, $locked, ['member_id' => $seat->id, 'status' => CommitteeMembershipStatus::Disabled->value], [
                'status' => CommitteeMembershipStatus::Excluded->value,
                'assessor_user_id' => $seat->assessor_user_id,
                'voided_requests' => $affected,
                'recomputed_requests' => $recomputed,
            ]));

            return new RosterChangeOutcome($seat, $affected, $recomputed);
        });
    }

    /**
     * @return Builder<CommitteeAssignment>
     */
    private function openRowsOf(CycleCommitteeAssessor $seat): Builder
    {
        return CommitteeAssignment::query()
            ->where('cycle_committee_assessor_id', $seat->id)
            ->whereIn('assessment_id', Assessment::query()->select('id')->whereIn('status', AdvancedAssessmentStatus::valuesOf(AdvancedAssessmentStatus::QualifiedForCommittee, ...AdvancedAssessmentStatus::committeeStates())));
    }
}
