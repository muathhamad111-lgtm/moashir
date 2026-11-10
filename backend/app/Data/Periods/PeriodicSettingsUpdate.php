<?php

declare(strict_types=1);

namespace App\Data\Periods;

use App\Enums\Periods\CertificateValidityRule;
use App\Enums\Periods\PeriodCadence;
use App\Enums\Periods\PeriodicAudience;
use App\Enums\Settings\SettingKey;

final readonly class PeriodicSettingsUpdate
{
    /**
     * @param  list<int>|null  $reminderDays
     */
    public function __construct(
        public ?PeriodCadence $cadence = null,
        public ?int $noticeDays = null,
        public ?array $reminderDays = null,
        public ?PeriodicAudience $audience = null,
        public ?CertificateValidityRule $certificateValidity = null,
        public ?int $paidDraftReminderDays = null,
        public ?string $reason = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->cadence === null && $this->noticeDays === null && $this->reminderDays === null && $this->audience === null
            && $this->certificateValidity === null && $this->paidDraftReminderDays === null;
    }

    public function appliedTo(PeriodicSettings $current): PeriodicSettings
    {
        $cadence = $this->cadence ?? $current->cadence;
        $resetsDays = $cadence !== $current->cadence;
        $reminders = $this->reminderDays ?? ($resetsDays ? $cadence->defaultReminderDays() : $current->reminderDays);
        rsort($reminders);

        return new PeriodicSettings(
            $cadence,
            $this->noticeDays ?? ($resetsDays ? $cadence->defaultNoticeDays() : $current->noticeDays),
            $reminders,
            $this->audience ?? $current->audience,
            $this->certificateValidity ?? $current->certificateValidity,
            $this->paidDraftReminderDays ?? $current->paidDraftReminderDays,
            $current->rolloutFrom,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function storedValuesOf(PeriodicSettings $settings): array
    {
        return [
            SettingKey::PeriodicCadence->value => $settings->cadence->value,
            SettingKey::PeriodicNoticeDaysBefore->value => $settings->noticeDays,
            SettingKey::PeriodicReminderDaysBeforeEnd->value => $settings->reminderDays,
            SettingKey::PeriodicAudience->value => $settings->audience->value,
            SettingKey::PeriodicCertificateValidity->value => $settings->certificateValidity->value,
            SettingKey::PeriodicPaidDraftReminderDays->value => $settings->paidDraftReminderDays,
        ];
    }
}
