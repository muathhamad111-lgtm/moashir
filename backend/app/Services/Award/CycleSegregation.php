<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Exceptions\Award\AwardRejected;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentAssignment;
use App\Models\Identity\User;

final readonly class CycleSegregation
{
    public function __construct(private CycleMembership $membership) {}

    public function assertMayJoinAsMember(int $cycleId, int $userId, string $field): void
    {
        if ($this->assessesInitially($cycleId, $userId) || $this->membership->isCommitteeAssessor($userId, $cycleId)) {
            throw AwardRejected::segregationConflict($field);
        }
    }

    public function assertMayAssessInitially(int $cycleId, int $userId, string $field): void
    {
        if ($this->membership->isMember($userId, $cycleId) || $this->membership->isCommitteeAssessor($userId, $cycleId)) {
            throw AwardRejected::segregationConflict($field);
        }
    }

    public function assertMayJoinCommittee(int $cycleId, int $userId, string $field): void
    {
        if ($this->conflictsWithCommittee($cycleId, $userId)) {
            throw AwardRejected::segregationConflict($field);
        }
    }

    public function assertMayActOnCommittee(int $cycleId, int $userId): void
    {
        if ($this->conflictsWithCommittee($cycleId, $userId)) {
            throw AwardRejected::segregationConflictOnAction();
        }
    }

    public function conflictsWithCommittee(int $cycleId, int $userId): bool
    {
        return $this->membership->isMember($userId, $cycleId) || $this->assessesInitially($cycleId, $userId);
    }

    public function assertMayDecideOn(User $actor, Assessment $assessment): void
    {
        $assessed = AssessmentAssignment::query()->where('assessment_id', $assessment->id)->where('assessor_user_id', $actor->id)->exists();

        if ($assessed) {
            throw AwardRejected::segregationConflictOnAction();
        }
    }

    private function assessesInitially(int $cycleId, int $userId): bool
    {
        return $this->membership->isInitialAssessor($userId, $cycleId) || $this->hasInitialAssignmentIn($cycleId, $userId);
    }

    private function hasInitialAssignmentIn(int $cycleId, int $userId): bool
    {
        return AssessmentAssignment::query()
            ->where('assessor_user_id', $userId)
            ->whereIn('assessment_id', Assessment::query()->select('id')->inCycle($cycleId))
            ->exists();
    }
}
