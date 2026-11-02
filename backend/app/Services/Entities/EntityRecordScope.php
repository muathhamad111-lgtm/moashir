<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Enums\Entities\EntityAccess;
use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Builder;

final class EntityRecordScope
{
    private const array FULL_ACCESS_ROLES = [RoleKey::SystemAdmin, RoleKey::RequestFollower];

    private const array CONTACT_ACCESS_ROLES = [RoleKey::FinanceOfficer];

    public function accessOf(User $user): EntityAccess
    {
        if ($user->isSuperAdmin()) {
            return EntityAccess::Full;
        }

        if (! $user->can(PermissionKey::EntitiesView->value)) {
            return EntityAccess::None;
        }

        $user->loadMissing('roles');
        $roles = $user->roleKeys();

        return match (true) {
            $this->holdsAny($roles, self::FULL_ACCESS_ROLES) => EntityAccess::Full,
            $this->holdsAny($roles, self::CONTACT_ACCESS_ROLES) => EntityAccess::Contact,
            default => EntityAccess::None,
        };
    }

    /**
     * @param  Builder<Entity>  $query
     */
    public function restrict(Builder $query, User $user): void
    {
        if ($this->accessOf($user) === EntityAccess::None) {
            $query->whereRaw('false');
        }
    }

    /**
     * @param  list<RoleKey>  $held
     * @param  list<RoleKey>  $wanted
     */
    private function holdsAny(array $held, array $wanted): bool
    {
        return array_any($wanted, fn (RoleKey $role): bool => in_array($role, $held, true));
    }
}
