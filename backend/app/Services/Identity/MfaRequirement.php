<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Enums\Identity\RoleKey;

final class MfaRequirement
{
    /**
     * @param  list<RoleKey>  $roles
     */
    public function isRequiredFor(array $roles): bool
    {
        return array_any($roles, fn (RoleKey $role): bool => $role->isStaff());
    }
}
