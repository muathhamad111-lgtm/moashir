<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Enums\Settings\SettingKey;
use App\Models\Identity\User;
use App\Services\Settings\SettingsStore;

final readonly class EntitySecondFactor
{
    public function __construct(private SettingsStore $store) {}

    public function isEnabled(): bool
    {
        return $this->store->get(SettingKey::EntitySecondFactorEnabled) !== false;
    }

    public function appliesTo(User $user): bool
    {
        return $user->isEntityUser() && $this->isEnabled();
    }
}
