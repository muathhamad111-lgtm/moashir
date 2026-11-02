<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Models\Identity\Permission;
use App\Models\Identity\Role;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Collection;

final class GetOwnPermissions
{
    /**
     * @return array{roles: Collection<int, Role>, permissions: Collection<int, Permission>}
     */
    public function handle(User $user): array
    {
        $user->loadMissing('roles');

        $permissions = $user->isSuperAdmin()
            ? Permission::query()->orderBy('sort_order')->get()
            : Permission::query()
                ->whereHas('roles', static fn ($query) => $query->whereIn('roles.id', $user->roles->modelKeys()))
                ->orderBy('sort_order')
                ->get();

        return ['roles' => $user->roles, 'permissions' => $permissions];
    }
}
