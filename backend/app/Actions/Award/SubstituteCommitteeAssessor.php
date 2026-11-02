<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\CandidateRole;
use App\Enums\Award\CommitteeMembershipStatus;
use App\Events\Award\CommitteeAssessorSubstituted;
use App\Events\Award\CommitteeRequestsDistributed;
use App\Exceptions\Award\CommitteeRejected;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CycleCommitteeAssessor;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Award\CommitteeCycleDistributor;
use App\Services\Award\CommitteeDistributor;
use App\Services\Award\CommitteeRoster;
use App\Services\Award\CommitteeRosterGate;
use App\Services\Award\CycleSegregation;
use App\Services\Award\CycleStaffEligibility;
use Illuminate\Support\Facades\DB;

final readonly class SubstituteCommitteeAssessor
{
    private const string VERB = 'substitute_committee_assessor';

    private const string FIELD = 'substitute_user_id';

    public function __construct(
        private CommitteeRosterGate $gate,
        private CycleStaffEligibility $eligibility,
        private CommitteeRoster $roster,
        private CycleSegregation $segregation,
        private CommitteeDistributor $distributor,
        private CommitteeCycleDistributor $cycleDistributor,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, AwardCycle $cycle, CycleCommitteeAssessor $member, int $substituteUserId, ?string $reason): CycleCommitteeAssessor
    {
        return DB::transaction(function () use ($actor, $cycle, $member, $substituteUserId, $reason): CycleCommitteeAssessor {
            $locked = $this->gate->enter($actor, $cycle, self::VERB, $reason);
            $replaced = $this->gate->member($member, CommitteeMembershipStatus::Disabled, self::VERB);
            $this->assertEligible($locked, $replaced, $substituteUserId);

            $replaced->forceFill(['status' => CommitteeMembershipStatus::Substituted])->save();
            $substitute = CycleCommitteeAssessor::query()->create([
                'award_cycle_id' => $locked->id,
                'assessor_user_id' => $substituteUserId,
                'status' => CommitteeMembershipStatus::Active,
                'replaces_id' => $replaced->id,
                'appointed_by_user_id' => $actor->id,
            ]);
            $seats = $this->seatReplacedRows($replaced, $substitute);
            $distribution = $this->cycleDistributor->distributeAll($locked, $actor->id);

            $this->audit->record(new AuditEntry(AuditEvent::CommitteeAssessorSubstituted, $locked, ['member_id' => $replaced->id, 'status' => CommitteeMembershipStatus::Disabled->value], [
                'status' => CommitteeMembershipStatus::Substituted->value,
                'substitute_member_id' => $substitute->id,
                'substitute_user_id' => $substituteUserId,
                'substitution_assignment_ids' => $seats,
            ]));

            CommitteeAssessorSubstituted::dispatch($substitute, $replaced, $actor->id);

            if (! $distribution->isEmpty()) {
                CommitteeRequestsDistributed::dispatch($distribution, $actor->id);
            }

            return $substitute;
        });
    }

    private function assertEligible(AwardCycle $cycle, CycleCommitteeAssessor $replaced, int $substituteUserId): void
    {
        if ($replaced->isHeldBy($substituteUserId)) {
            throw CommitteeRejected::candidateInvalid(self::FIELD);
        }

        $this->eligibility->find(CandidateRole::CommitteeAssessor, $substituteUserId) ?? throw CommitteeRejected::candidateInvalid(self::FIELD);

        if ($this->roster->seatOf($cycle->id, $substituteUserId) instanceof CycleCommitteeAssessor) {
            throw CommitteeRejected::alreadySeated(self::FIELD);
        }

        $this->segregation->assertMayJoinCommittee($cycle->id, $substituteUserId, self::FIELD);
    }

    /**
     * @return list<int>
     */
    private function seatReplacedRows(CycleCommitteeAssessor $replaced, CycleCommitteeAssessor $substitute): array
    {
        $rows = CommitteeAssignment::query()
            ->with('assessment')
            ->where('cycle_committee_assessor_id', $replaced->id)
            ->live()
            ->whereIn('assessment_id', Assessment::query()->select('id')->whereIn('status', AdvancedAssessmentStatus::valuesOf(...AdvancedAssessmentStatus::committeeStates())))
            ->orderBy('id')
            ->get();

        return array_values($rows->map(fn (CommitteeAssignment $row): int => $this->distributor->seat($substitute, $row->assessment, $row)->id)->all());
    }
}
