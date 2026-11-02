<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Data\Audit\AuditEntry;
use App\Data\Award\CommitteeDistribution;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\CommitteeAssignmentStatus;
use App\Models\Assessments\Assessment;
use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CycleCommitteeAssessor;
use App\Services\Assessments\AssessmentStateMachine;
use App\Services\Audit\AuditRecorder;
use App\Support\ScalarValue;

final readonly class CommitteeDistributor
{
    public function __construct(
        private CommitteeRoster $roster,
        private CommitteeProgressSync $progress,
        private AssessmentStateMachine $stateMachine,
        private AuditRecorder $audit,
    ) {}

    public function distribute(Assessment $locked, ?int $actorId): CommitteeDistribution
    {
        $cycleId = (int) $locked->award_cycle_id;
        $status = $locked->status;

        if (! $status instanceof AdvancedAssessmentStatus || $status !== AdvancedAssessmentStatus::QualifiedForCommittee && ! $status->isInCommittee()) {
            return new CommitteeDistribution($cycleId);
        }

        $seated = $this->seatedMemberIds($locked);
        $counts = [];

        foreach ($this->roster->activeMembers($cycleId) as $member) {
            if (! in_array($member->id, $seated, true)) {
                $this->seat($member, $locked);
                $counts[$member->assessor_user_id] = 1;
            }
        }

        $this->advance($locked, $status, $counts, $actorId);

        return new CommitteeDistribution($cycleId, $counts);
    }

    public function seat(CycleCommitteeAssessor $member, Assessment $assessment, ?CommitteeAssignment $replaces = null): CommitteeAssignment
    {
        return CommitteeAssignment::query()->create([
            'award_cycle_id' => $member->award_cycle_id,
            'cycle_committee_assessor_id' => $member->id,
            'assessment_id' => $assessment->id,
            'status' => CommitteeAssignmentStatus::NotStarted,
            'replaces_assignment_id' => $replaces?->id,
        ]);
    }

    /**
     * @param  array<int, int>  $counts
     */
    private function advance(Assessment $locked, AdvancedAssessmentStatus $status, array $counts, ?int $actorId): void
    {
        if ($status === AdvancedAssessmentStatus::QualifiedForCommittee) {
            if ($counts === []) {
                return;
            }

            $this->stateMachine->transition($locked, AdvancedAssessmentStatus::UnderCommitteeAssessment, AssessmentVerb::Distribute, $actorId);
        }

        if ($counts !== []) {
            $this->audit->record(new AuditEntry(AuditEvent::AwardDistributed, $locked, ['status' => $status->value], [
                'status' => $locked->status->value,
                'committee_assessor_ids' => array_keys($counts),
            ]));
        }

        $this->progress->sync($locked, $actorId);
    }

    /**
     * @return list<int>
     */
    private function seatedMemberIds(Assessment $assessment): array
    {
        return array_values(CommitteeAssignment::query()
            ->where('assessment_id', $assessment->id)
            ->pluck('cycle_committee_assessor_id')
            ->map(static fn (mixed $id): int => ScalarValue::int($id))
            ->all());
    }
}
