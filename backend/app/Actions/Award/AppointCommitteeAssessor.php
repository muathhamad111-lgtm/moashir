<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\CandidateRole;
use App\Enums\Award\CommitteeMembershipStatus;
use App\Events\Award\CommitteeRequestsDistributed;
use App\Exceptions\Award\CommitteeRejected;
use App\Models\Award\AwardCycle;
use App\Models\Award\CycleCommitteeAssessor;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Award\CommitteeCycleDistributor;
use App\Services\Award\CommitteeRoster;
use App\Services\Award\CommitteeRosterGate;
use App\Services\Award\CycleSegregation;
use App\Services\Award\CycleStaffEligibility;
use Illuminate\Support\Facades\DB;

final readonly class AppointCommitteeAssessor
{
    private const string FIELD = 'user_id';

    public function __construct(
        private CommitteeRosterGate $gate,
        private CycleStaffEligibility $eligibility,
        private CommitteeRoster $roster,
        private CycleSegregation $segregation,
        private CommitteeCycleDistributor $distributor,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, AwardCycle $cycle, int $userId, ?string $reason): CycleCommitteeAssessor
    {
        return DB::transaction(function () use ($actor, $cycle, $userId, $reason): CycleCommitteeAssessor {
            $locked = $this->gate->enter($actor, $cycle, 'appoint_committee_assessor', $reason);
            $this->eligibility->find(CandidateRole::CommitteeAssessor, $userId) ?? throw CommitteeRejected::candidateInvalid(self::FIELD);

            if ($this->roster->seatOf($locked->id, $userId) instanceof CycleCommitteeAssessor) {
                throw CommitteeRejected::alreadySeated(self::FIELD);
            }

            $this->segregation->assertMayJoinCommittee($locked->id, $userId, self::FIELD);
            $member = CycleCommitteeAssessor::query()->create([
                'award_cycle_id' => $locked->id,
                'assessor_user_id' => $userId,
                'status' => CommitteeMembershipStatus::Active,
                'appointed_by_user_id' => $actor->id,
            ]);
            $this->audit->record(new AuditEntry(AuditEvent::CommitteeAssessorAppointed, $locked, [], ['member_id' => $member->id, 'assessor_user_id' => $userId]));

            $distribution = $this->distributor->distributeAll($locked, $actor->id);

            if (! $distribution->isEmpty()) {
                CommitteeRequestsDistributed::dispatch($distribution, $actor->id);
            }

            return $member;
        });
    }
}
