<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Enums\Award\CycleMemberRole;
use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Builder;

final readonly class CycleRecordScope
{
    private const array GLOBAL_READER_ROLES = [RoleKey::SystemAdmin, RoleKey::RequestFollower];

    public function __construct(private CycleMembership $membership) {}

    public function readsEveryCycle(User $user): bool
    {
        return $user->isSuperAdmin() || ($user->can(PermissionKey::AwardCyclesView->value) && $this->holdsAnyRole($user, self::GLOBAL_READER_ROLES));
    }

    public function managesEveryCycle(User $user): bool
    {
        return $user->isSuperAdmin() || ($user->can(PermissionKey::AwardCyclesManage->value) && $this->holdsAnyRole($user, [RoleKey::SystemAdmin]));
    }

    public function canView(User $user, AwardCycle $cycle): bool
    {
        if ($this->readsEveryCycle($user)) {
            return true;
        }

        return $user->can(PermissionKey::AwardCyclesView->value)
            && ($this->membership->isMember($user->id, $cycle->id)
                || $this->membership->isInitialAssessor($user->id, $cycle->id)
                || $this->membership->isCommitteeAssessor($user->id, $cycle->id));
    }

    public function canManage(User $user, AwardCycle $cycle): bool
    {
        if ($this->managesEveryCycle($user)) {
            return true;
        }

        return $user->can(PermissionKey::AwardCyclesManage->value) && $this->membership->isMember($user->id, $cycle->id, CycleMemberRole::AwardSecretary);
    }

    public function canViewFiltering(User $user, AwardCycle $cycle): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $user->can(PermissionKey::AwardFilteringView->value)) {
            return false;
        }

        return $this->holdsAnyRole($user, [RoleKey::SystemAdmin])
            || $this->membership->isMember($user->id, $cycle->id, CycleMemberRole::FilteringOfficer, CycleMemberRole::AwardSecretary);
    }

    public function canDecideFiltering(User $user, AwardCycle $cycle): bool
    {
        return $user->isSuperAdmin()
            || ($user->can(PermissionKey::AwardFilteringDecide->value) && $this->membership->isMember($user->id, $cycle->id, CycleMemberRole::FilteringOfficer));
    }

    /**
     * @param  Builder<AwardCycle>  $query
     */
    public function restrict(Builder $query, User $user): void
    {
        if ($this->readsEveryCycle($user)) {
            return;
        }

        if (! $user->can(PermissionKey::AwardCyclesView->value)) {
            $query->whereRaw('false');

            return;
        }

        $query->where(static fn (Builder $visible): Builder => $visible
            ->whereHas('members', static fn (Builder $member): Builder => $member->where('user_id', $user->id))
            ->orWhereHas('initialAssessors', static fn (Builder $link): Builder => $link->where('assessor_user_id', $user->id))
            ->orWhereHas('committeeMembers', static fn (Builder $seat): Builder => $seat->where('assessor_user_id', $user->id)->seated()));
    }

    /**
     * @param  list<RoleKey>  $roles
     */
    private function holdsAnyRole(User $user, array $roles): bool
    {
        $user->loadMissing('roles');

        return array_any($roles, static fn (RoleKey $role): bool => in_array($role, $user->roleKeys(), true));
    }
}
