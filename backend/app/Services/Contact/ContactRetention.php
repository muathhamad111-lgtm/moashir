<?php

declare(strict_types=1);

namespace App\Services\Contact;

use App\Enums\Settings\SettingKey;
use App\Services\Settings\SettingsStore;
use App\Support\ScalarValue;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

final readonly class ContactRetention
{
    public function __construct(private SettingsStore $settings) {}

    public function months(): int
    {
        return ScalarValue::int($this->settings->get(SettingKey::ContactRetentionMonths), ScalarValue::int(SettingKey::ContactRetentionMonths->defaultValue()));
    }

    public function closedBefore(): CarbonInterface
    {
        return Date::now()->subMonthsNoOverflow($this->months());
    }
}
