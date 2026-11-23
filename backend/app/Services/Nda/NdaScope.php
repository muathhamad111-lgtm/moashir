<?php

declare(strict_types=1);

namespace App\Services\Nda;

use App\Enums\Identity\RoleKey;
use App\Enums\Settings\SettingKey;
use App\Models\Identity\User;
use App\Services\Settings\SettingsStore;
use App\Support\StringList;

/**
 * Who must accept the staff non-disclosure agreement: the staff roles the administrator put in scope, and every
 * Super Admin. Entity users are never in scope.
 */
final readonly class NdaScope
{
    public function __construct(private SettingsStore $store) {}

    /**
     * @return list<RoleKey>
     */
    public function roles(): array
    {
        $stored = StringList::from($this->store->get(SettingKey::NdaRoles));

        return array_values(array_filter(
            RoleKey::cases(),
            static fn (RoleKey $role): bool => $role->isStaff() && in_array($role->value, $stored, true),
        ));
    }

    /**
     * @return list<string>
     */
    public function roleValues(): array
    {
        return array_map(static fn (RoleKey $role): string => $role->value, $this->roles());
    }

    public function covers(User $user): bool
    {
        return $this->coversWithin($user, $this->roles());
    }

    /**
     * The same test against roles the caller already read, so a list of users costs one settings read.
     *
     * @param  list<RoleKey>  $inScope
     */
    public function coversWithin(User $user, array $inScope): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return ! $user->isEntityUser() && array_any($user->roleKeys(), static fn (RoleKey $role): bool => in_array($role, $inScope, true));
    }
}
