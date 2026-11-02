<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Data\Identity\StaffActor;
use App\Models\Identity\Role;
use App\Models\Identity\User;

final class StaffActorReader
{
    /**
     * @param  list<int>  $userIds
     * @return array<int, StaffActor>
     */
    public function for(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $actors = [];

        foreach (User::withTrashed()->with('roles')->whereKey(array_values(array_unique($userIds)))->get() as $user) {
            $actors[$user->id] = $this->actorOf($user);
        }

        return $actors;
    }

    private function actorOf(User $user): StaffActor
    {
        if ($user->trashed() || $user->isSuperAdmin()) {
            return StaffActor::unknown();
        }

        $roles = array_values($user->roles
            ->sortBy(static fn (Role $role): int => $role->dashboard_priority)
            ->map(static fn (Role $role): array => ['key' => $role->name, 'label' => $role->label])
            ->all());

        return new StaffActor($user->name, $roles);
    }
}
