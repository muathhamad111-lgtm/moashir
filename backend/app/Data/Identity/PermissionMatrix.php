<?php

declare(strict_types=1);

namespace App\Data\Identity;

use App\Models\Identity\Permission;
use App\Models\Identity\Role;
use Illuminate\Database\Eloquent\Collection;

final readonly class PermissionMatrix
{
    /**
     * @param  Collection<int, Permission>  $permissions
     * @param  Collection<int, Role>  $roles
     */
    public function __construct(public Collection $permissions, public Collection $roles) {}

    /**
     * @return array<string, list<string>>
     */
    public function grants(): array
    {
        return $this->roles
            ->mapWithKeys(static fn (Role $role): array => [$role->name => $role->permissionKeys()])
            ->all();
    }
}
