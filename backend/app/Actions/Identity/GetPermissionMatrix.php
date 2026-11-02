<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Identity\PermissionMatrix;
use App\Enums\Identity\PermissionModule;
use App\Models\Identity\Permission;
use App\Models\Identity\Role;

final class GetPermissionMatrix
{
    public function handle(?PermissionModule $module): PermissionMatrix
    {
        $permissions = Permission::query()
            ->when($module instanceof PermissionModule, static fn ($query) => $query->where('module', $module))
            ->orderBy('sort_order')
            ->get();

        $roles = Role::query()->with('permissions')->orderBy('dashboard_priority')->get();

        return new PermissionMatrix($permissions, $roles);
    }
}
