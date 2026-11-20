<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Data\Identity\NationalIdStatus;
use App\Enums\Identity\RoleKey;
use App\Enums\Settings\SettingKey;
use App\Models\Identity\User;
use App\Services\Settings\SettingsStore;
use App\Support\Identity\AccountNationalId;
use App\Support\StringList;

final readonly class NationalIdRequirement
{
    public function __construct(private SettingsStore $store) {}

    public function isEnabled(): bool
    {
        return $this->store->get(SettingKey::NationalIdEnabled) === true;
    }

    /**
     * @return list<RoleKey>
     */
    public function requiredRoles(): array
    {
        $stored = StringList::from($this->store->get(SettingKey::NationalIdRoles));

        return array_values(array_filter(RoleKey::cases(), static fn (RoleKey $role): bool => in_array($role->value, $stored, true)));
    }

    public function isRequiredFor(User $user): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return $user->isSuperAdmin() || $this->coversAny($user->roleKeys());
    }

    /**
     * @param  list<RoleKey>  $roles
     */
    public function isRequiredForRoles(array $roles): bool
    {
        return $this->isEnabled() && $this->coversAny($roles);
    }

    public function statusOf(User $user): NationalIdStatus
    {
        return new NationalIdStatus($this->isRequiredFor($user), AccountNationalId::of($user)?->masked());
    }

    public function isMissingFor(User $user): bool
    {
        return ! AccountNationalId::isRecorded($user) && $this->isRequiredFor($user);
    }

    /**
     * @param  list<RoleKey>  $roles
     */
    private function coversAny(array $roles): bool
    {
        $required = $this->requiredRoles();

        return array_any($roles, static fn (RoleKey $role): bool => in_array($role, $required, true));
    }
}
