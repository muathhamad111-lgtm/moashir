<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Enums\Award\AwardCycleConcurrency;
use App\Enums\Settings\SettingKey;
use App\Services\Settings\SettingsStore;
use App\Support\ScalarValue;

final readonly class AwardCycleConcurrencyReader
{
    public function __construct(private SettingsStore $settings) {}

    public function read(): AwardCycleConcurrency
    {
        return AwardCycleConcurrency::tryFrom(ScalarValue::string($this->settings->get(SettingKey::AwardCycleConcurrency)))
            ?? AwardCycleConcurrency::Single;
    }
}
