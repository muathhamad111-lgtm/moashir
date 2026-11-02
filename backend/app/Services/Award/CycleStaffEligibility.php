<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Enums\Award\CandidateRole;
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
        return User::query()
            ->permission($role->permission()->value)
            ->staff()
            ->withoutSuperAdmins()
            ->where('status', UserStatus::Active);
    }

    public function find(CandidateRole $role, int $userId): ?User
    {
        $candidate = $this->candidates($role)->whereKey($userId)->first();

        return $candidate instanceof User && ! $candidate->isLocked() ? $candidate : null;
    }
}
