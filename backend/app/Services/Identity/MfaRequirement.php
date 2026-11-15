<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Enums\Identity\RoleKey;
use App\Enums\Settings\SettingKey;
use App\Services\Settings\SettingsStore;
use App\Support\StringList;

final readonly class MfaRequirement
{
    public function __construct(private SettingsStore $store) {}

    /**
     * @param  list<RoleKey>  $roles
     */
    public function isRequiredFor(array $roles): bool
    {
        if ($this->store->get(SettingKey::TwoFactorEnabled) === false) {
            return false;
        }

        $required = $this->requiredRoles();

        return array_any($roles, static fn (RoleKey $role): bool => in_array($role, $required, true));
    }

    public function isEnabled(): bool
    {
        return $this->store->get(SettingKey::TwoFactorEnabled) !== false;
    }

    /**
     * @return list<RoleKey>
     */
    public function requiredRoles(): array
    {
        $stored = StringList::from($this->store->get(SettingKey::TwoFactorRoles));

        return array_values(array_filter(RoleKey::cases(), static fn (RoleKey $role): bool => in_array($role->value, $stored, true)));
    }
}
