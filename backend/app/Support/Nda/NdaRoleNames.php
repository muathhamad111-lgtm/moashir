<?php

declare(strict_types=1);

namespace App\Support\Nda;

use App\Enums\Identity\RoleKey;
use App\Models\Identity\User;

/**
 * The role names an agreement acceptance records and the register shows: the user's roles, led by `super_admin`
 * for a Super Admin (who holds no role of their own but is always in scope).
 */
final class NdaRoleNames
{
    public const string SUPER_ADMIN = 'super_admin';

    /**
     * @return list<string>
     */
    public static function of(User $user): array
    {
        $roles = array_map(static fn (RoleKey $role): string => $role->value, $user->roleKeys());

        return $user->isSuperAdmin() ? [self::SUPER_ADMIN, ...$roles] : $roles;
    }

    public static function labelKey(string $role): string
    {
        return $role === self::SUPER_ADMIN ? 'nda.super_admin' : 'roles.'.$role.'.label';
    }
}
