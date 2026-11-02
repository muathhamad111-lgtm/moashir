<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Enums\Identity\RoleKey;
use App\Models\Identity\User;

final class ActorRoles
{
    public const string SUPER_ADMIN_MARKER = 'super_admin';

    /**
     * @return list<string>
     */
    public static function of(User $actor): array
    {
        $roles = array_map(static fn (RoleKey $role): string => $role->value, $actor->roleKeys());

        return $actor->isSuperAdmin() ? [self::SUPER_ADMIN_MARKER, ...$roles] : $roles;
    }
}
