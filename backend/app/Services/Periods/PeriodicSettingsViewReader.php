<?php

declare(strict_types=1);

namespace App\Services\Periods;

use App\Data\Periods\PeriodicSettingsView;
use App\Enums\Settings\SettingGroup;
use App\Models\Settings\Setting;
use Illuminate\Support\Carbon;

final readonly class PeriodicSettingsViewReader
{
    public function __construct(private PeriodicSettingsReader $settings, private PeriodLocator $periods) {}

    public function read(): PeriodicSettingsView
    {
        $updatedAt = Setting::query()->where('group', SettingGroup::Periodic)->max('updated_at');

        return new PeriodicSettingsView(
            $this->settings->read(),
            $this->periods->current(),
            array_values($this->periods->upcoming()->all()),
            is_string($updatedAt) ? Carbon::parse($updatedAt) : null,
        );
    }
}
