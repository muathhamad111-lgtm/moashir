<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Identity\AssessorQualification;
use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\UserStatus;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Models\Award\AwardCycleAssessor;
use App\Models\Award\AwardCycleMember;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

final class AssessorEligibility
{
    /**
     * @return Builder<User>
     */
    public function eligibleFor(User $actor, AssessmentPath $path, ?int $cycleId = null): Builder
    {
        $query = $this->activeAssessors($actor)->qualifiedFor($this->qualificationFor($path));

        if ($cycleId !== null) {
            $this->restrictToCycle($query, $cycleId);
        }

        return $query;
    }

    public function assertEligible(User $actor, int $candidateId, AssessmentPath $path, ?int $cycleId = null): User
    {
        if ($candidateId === $actor->id) {
            throw AssessmentRejected::selfAssignment();
        }

        $candidate = $this->activeAssessor($actor, $candidateId) ?? throw AssessmentRejected::assessorInvalid();
        $qualification = $this->qualificationFor($path);

        if (! $candidate->isQualifiedFor($qualification)) {
            throw AssessmentRejected::assessorNotQualified($qualification);
        }

        if ($cycleId !== null && $this->isCycleMember($cycleId, $candidate->id)) {
            throw AssessmentRejected::assessorConflict();
        }

        if ($cycleId !== null && ! $this->isLinkedTo($cycleId, $candidate->id)) {
            throw AssessmentRejected::assessorNotInCycle();
        }

        return $candidate;
    }

    public function find(User $actor, int $candidateId, AssessmentPath $path): ?User
    {
        $candidate = $this->activeAssessor($actor, $candidateId);

        return $candidate instanceof User && $candidate->isQualifiedFor($this->qualificationFor($path)) ? $candidate : null;
    }

    private function activeAssessor(User $actor, int $candidateId): ?User
    {
        $candidate = $this->activeAssessors($actor)->whereKey($candidateId)->first();

        return $candidate instanceof User && ! $candidate->isLocked() ? $candidate : null;
    }

    /**
     * @return Builder<User>
     */
    private function activeAssessors(User $actor): Builder
    {
        return User::query()
            ->permission(PermissionKey::AssessmentScoreManage->value)
            ->staff()
            ->withoutSuperAdmins()
            ->where('status', UserStatus::Active)
            ->whereKeyNot($actor->id);
    }

    private function qualificationFor(AssessmentPath $path): AssessorQualification
    {
        return AssessorQualification::forPath($path) ?? throw new LogicException('The self assessment path has no assessors.');
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
