<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Enums\Audit\AuditEvent;
use App\Enums\Settings\SettingKey;
use App\Services\Periods\PeriodicSettingsReader;
use App\Services\Periods\PeriodSchedule;
use App\Services\Settings\SettingsStore;
use Carbon\CarbonImmutable;

final readonly class DemoPeriodInstaller
{
    public const string DEMO_ROLLOUT = '2025-10-01';

    public function __construct(
        private PeriodicSettingsReader $settings,
        private SettingsStore $store,
        private PeriodSchedule $schedule,
    ) {}

    public function prepare(): void
    {
        $rollout = $this->settings->read()->rolloutFrom;

        if (! $rollout instanceof CarbonImmutable || $rollout->toDateString() > self::DEMO_ROLLOUT) {
            $this->store->put(SettingKey::PeriodicRolloutFrom, self::DEMO_ROLLOUT, AuditEvent::PeriodicSettingsUpdated, null);
        }

        $this->schedule->sync();
    }
}
