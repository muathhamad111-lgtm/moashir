<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Enums\Award\CandidateRole;
use App\Enums\Identity\AssessorQualification;
use App\Enums\Identity\UserStatus;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Builder;

final class CycleStaffEligibility
{
    /**
     * @return Builder<User>
     */
    public function candidates(CandidateRole $role): Builder
    {
        $qualification = $role->qualification();
        $query = $this->holders($role);

        if ($qualification instanceof AssessorQualification) {
            $query->qualifiedFor($qualification);
        }

        return $query;
    }

    public function find(CandidateRole $role, int $userId): ?User
    {
        $candidate = $this->holders($role)->whereKey($userId)->first();

        return $candidate instanceof User && ! $candidate->isLocked() ? $candidate : null;
    }

    public function isQualified(CandidateRole $role, User $candidate): bool
    {
        $qualification = $role->qualification();

        return ! $qualification instanceof AssessorQualification || $candidate->isQualifiedFor($qualification);
    }

    /**
     * @return Builder<User>
     */
    private function holders(CandidateRole $role): Builder
    {
        return User::query()
            ->permission($role->permission()->value)
            ->staff()
            ->withoutSuperAdmins()
            ->where('status', UserStatus::Active);
    }
}
