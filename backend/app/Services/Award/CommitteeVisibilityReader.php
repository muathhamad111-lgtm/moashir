<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Enums\Award\CommitteeEntityVisibility;
use App\Enums\Settings\SettingKey;
use App\Services\Settings\SettingsStore;
use App\Support\ScalarValue;

final readonly class CommitteeVisibilityReader
{
    public function __construct(private SettingsStore $settings) {}

    public function read(): CommitteeEntityVisibility
    {
        return CommitteeEntityVisibility::tryFrom(ScalarValue::string($this->settings->get(SettingKey::CommitteeEntityDataVisibility)))
            ?? CommitteeEntityVisibility::NameOnly;
    }
}
