<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\UserStatus;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycleAssessor;
use App\Models\Award\AwardCycleMember;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Builder;

final class AssessorEligibility
{
    /**
     * @return Builder<User>
     */
    public function eligibleFor(User $actor, ?Assessment $assessment = null): Builder
    {
        $query = User::query()
            ->permission(PermissionKey::AssessmentScoreManage->value)
            ->staff()
            ->withoutSuperAdmins()
            ->where('status', UserStatus::Active)
            ->whereKeyNot($actor->id);

        if ($assessment?->award_cycle_id !== null) {
            $this->restrictToCycle($query, $assessment->award_cycle_id);
        }

        return $query;
    }

    public function assertEligible(User $actor, int $candidateId, ?Assessment $assessment = null): User
    {
        if ($candidateId === $actor->id) {
            throw AssessmentRejected::selfAssignment();
        }

        $candidate = $this->find($actor, $candidateId) ?? throw AssessmentRejected::assessorInvalid();
        $cycleId = $assessment?->award_cycle_id;

        if ($cycleId !== null && $this->isCycleMember($cycleId, $candidate->id)) {
            throw AssessmentRejected::assessorConflict();
        }

        if ($cycleId !== null && ! $this->isLinkedTo($cycleId, $candidate->id)) {
            throw AssessmentRejected::assessorNotInCycle();
        }

        return $candidate;
    }

    public function find(User $actor, int $candidateId): ?User
    {
        $candidate = $this->eligibleFor($actor)->whereKey($candidateId)->first();

        return $candidate instanceof User && ! $candidate->isLocked() ? $candidate : null;
    }

    /**
     * @param  Builder<User>  $query
     */
    private function restrictToCycle(Builder $query, int $cycleId): void
    {
        $query
            ->whereIn('id', AwardCycleAssessor::query()->select('assessor_user_id')->where('award_cycle_id', $cycleId)->active())
            ->whereNotIn('id', AwardCycleMember::query()->select('user_id')->where('award_cycle_id', $cycleId));
    }

    private function isCycleMember(int $cycleId, int $userId): bool
    {
        return AwardCycleMember::query()->where('award_cycle_id', $cycleId)->where('user_id', $userId)->exists();
    }

    private function isLinkedTo(int $cycleId, int $userId): bool
    {
        return AwardCycleAssessor::query()->where('award_cycle_id', $cycleId)->where('assessor_user_id', $userId)->active()->exists();
    }
}
