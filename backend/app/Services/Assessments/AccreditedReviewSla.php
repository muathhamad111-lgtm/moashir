<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Enums\Settings\SettingKey;
use App\Services\Settings\SettingsStore;
use App\Support\ScalarValue;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final readonly class AccreditedReviewSla
{
    public function __construct(private SettingsStore $settings) {}

    public function days(): int
    {
        return max(1, ScalarValue::int($this->settings->get(SettingKey::AccreditedReviewSlaDays), ScalarValue::int(SettingKey::AccreditedReviewSlaDays->defaultValue())));
    }

    public function dueAt(CarbonInterface $submittedAt): CarbonImmutable
    {
        return CarbonImmutable::instance($submittedAt)->addDays($this->days());
    }
}
