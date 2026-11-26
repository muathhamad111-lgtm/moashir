<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Enums\Settings\SettingKey;

final readonly class GeneralEmailNotifications
{
    public function __construct(private SettingsStore $store) {}

    public function isEnabled(): bool
    {
        return $this->store->get(SettingKey::GeneralEmailNotifications) !== false;
    }
}
