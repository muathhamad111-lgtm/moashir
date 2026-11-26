<?php

declare(strict_types=1);

namespace App\Services\Periods;

use App\Data\Periods\PeriodicSettings;
use App\Enums\Periods\CertificateValidityRule;
use App\Enums\Periods\PeriodCadence;
use App\Enums\Periods\PeriodicAudience;
use App\Enums\Settings\SettingKey;
use App\Services\Settings\SettingsStore;
use App\Support\Periods\LocalDay;
use App\Support\ScalarValue;

final readonly class PeriodicSettingsReader
{
    public const array KEYS = [
        SettingKey::PeriodicCadence,
        SettingKey::PeriodicNoticeDaysBefore,
        SettingKey::PeriodicReminderDaysBeforeEnd,
        SettingKey::PeriodicAudience,
        SettingKey::PeriodicCertificateValidity,
        SettingKey::PeriodicPaidDraftReminderDays,
        SettingKey::PeriodicRolloutFrom,
    ];

    public function __construct(private SettingsStore $store) {}

    public function read(): PeriodicSettings
    {
        $values = $this->store->many(self::KEYS);
        $rollout = $values[SettingKey::PeriodicRolloutFrom->value];

        return new PeriodicSettings(
            PeriodCadence::tryFrom(ScalarValue::string($values[SettingKey::PeriodicCadence->value])) ?? PeriodCadence::Quarterly,
            ScalarValue::int($values[SettingKey::PeriodicNoticeDaysBefore->value]),
            $this->days($values[SettingKey::PeriodicReminderDaysBeforeEnd->value]),
            PeriodicAudience::tryFrom(ScalarValue::string($values[SettingKey::PeriodicAudience->value])) ?? PeriodicAudience::ActiveVerifiedEntities,
            CertificateValidityRule::tryFrom(ScalarValue::string($values[SettingKey::PeriodicCertificateValidity->value])) ?? CertificateValidityRule::NextPeriodEnd,
            ScalarValue::int($values[SettingKey::PeriodicPaidDraftReminderDays->value]),
            is_string($rollout) && $rollout !== '' ? LocalDay::parse($rollout) : null,
        );
    }

    /**
     * @return list<int>
     */
    private function days(mixed $stored): array
    {
        $days = array_map(static fn (mixed $day): int => ScalarValue::int($day), is_array($stored) ? array_values($stored) : []);
        rsort($days);

        return $days;
    }
}
