<?php

declare(strict_types=1);

namespace App\Http\Resources\Settings;

use App\Data\Periods\PeriodicSettingsView;
use App\Enums\Periods\PeriodCadence;
use App\Models\Assessments\AssessmentPeriod;
use App\Support\Periods\PeriodicSettingsRules;
use App\Support\Periods\PeriodPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read PeriodicSettingsView $resource
 */
final class PeriodicSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $view = $this->resource;
        $settings = $view->settings;
        $current = $view->current;

        return [
            'cadence' => $settings->cadence->value,
            'notice_days_before' => $settings->noticeDays,
            'reminder_days_before_end' => $settings->reminderDays,
            'audience' => $settings->audience->value,
            'certificate_validity' => $settings->certificateValidity->value,
            'paid_draft_reminder_days' => $settings->paidDraftReminderDays,
            'rollout_from' => $settings->rolloutFrom?->toDateString(),
            'cadence_effective_from' => $current instanceof AssessmentPeriod && $current->cadence !== $settings->cadence ? $current->ends_on->addDay()->toDateString() : null,
            'current_period' => PeriodPresenter::optional($current),
            'upcoming_periods' => array_map(static fn (AssessmentPeriod $period): ?array => PeriodPresenter::upcoming($period, $settings->noticeDays), $view->upcoming),
            'defaults' => self::defaults(),
            'limits' => self::limits(),
            'updated_at' => $view->updatedAt?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, array{notice_days_before: int, reminder_days_before_end: list<int>}>
     */
    private static function defaults(): array
    {
        $defaults = [];

        foreach (PeriodCadence::cases() as $cadence) {
            $defaults[$cadence->value] = ['notice_days_before' => $cadence->defaultNoticeDays(), 'reminder_days_before_end' => $cadence->defaultReminderDays()];
        }

        return $defaults;
    }

    /**
     * @return array{days_max: array<string, int>, reminders_max_items: int, paid_draft_reminder_days: array{min: int, max: int}}
     */
    private static function limits(): array
    {
        $daysMax = [];

        foreach (PeriodCadence::cases() as $cadence) {
            $daysMax[$cadence->value] = $cadence->maxDays();
        }

        return [
            'days_max' => $daysMax,
            'reminders_max_items' => PeriodicSettingsRules::MAX_REMINDERS,
            'paid_draft_reminder_days' => ['min' => 1, 'max' => PeriodicSettingsRules::MAX_PAID_DRAFT_REMINDER_DAYS],
        ];
    }
}
