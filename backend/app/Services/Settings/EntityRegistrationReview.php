<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Enums\Settings\SettingKey;

final readonly class EntityRegistrationReview
{
    public function __construct(private SettingsStore $store) {}

    public function isRequired(): bool
    {
        return $this->store->get(SettingKey::EntityRegistrationReview) !== false;
    }
}
