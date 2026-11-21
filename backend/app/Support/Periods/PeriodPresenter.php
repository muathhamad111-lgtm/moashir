<?php

declare(strict_types=1);

namespace App\Support\Periods;

use App\Data\Periods\AccreditedPeriodView;
use App\Data\Periods\PlannedPeriod;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Periods\PeriodAttribution;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentPeriod;
use App\Support\ValueObjects\LocalizedText;

final class PeriodPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function period(AssessmentPeriod $period): array
    {
        return [
            'id' => $period->id,
            'key' => $period->key,
            'cadence' => $period->cadence->value,
            'label' => $period->label()->toArray(),
            'starts_on' => $period->starts_on->toDateString(),
            'ends_on' => $period->ends_on->toDateString(),
            'starts_at' => $period->starts_at->toIso8601String(),
            'ends_at' => $period->ends_at->toIso8601String(),
            'is_shortened' => $period->is_shortened,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function optional(?AssessmentPeriod $period): ?array
    {
        return $period instanceof AssessmentPeriod ? self::period($period) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function upcoming(?AssessmentPeriod $period, int $noticeDays): ?array
    {
        return $period instanceof AssessmentPeriod ? [...self::period($period), 'notice_on' => $period->noticeOn($noticeDays)->toDateString()] : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function planned(PlannedPeriod $planned, int $noticeDays): array
    {
        $window = $planned->window;

        return [
            'id' => $planned->id,
            'key' => $window->key(),
            'cadence' => $window->cadence->value,
            'label' => $window->label()->toArray(),
            'starts_on' => $window->startsOn->toDateString(),
            'ends_on' => $window->endsOn->toDateString(),
            'starts_at' => $window->startsAt()->toIso8601String(),
            'ends_at' => $window->endsAt()->toIso8601String(),
            'is_shortened' => $window->isShortened(),
            'notice_on' => $window->startsOn->subDays($noticeDays)->toDateString(),
        ];
    }

    /**
     * @return array{period_state: string|null, period: array<string, mixed>|null, period_label: array{ar: string, en: string}|null}
     */
    public static function attribution(Assessment $assessment): array
    {
        if (! $assessment->isAccredited()) {
            return ['period_state' => null, 'period' => null, 'period_label' => null];
        }

        $attribution = self::attributionOf($assessment);
        $period = $attribution === PeriodAttribution::Assigned ? $assessment->period : null;

        return [
            'period_state' => $attribution->value,
            'period' => self::optional($period),
            'period_label' => self::labelOf($attribution, $period)?->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function view(AccreditedPeriodView $view): array
    {
        return [
            'is_live' => $view->isLive(),
            'rollout_from' => $view->settings->rolloutFrom?->toDateString(),
            'cadence' => $view->cadence()->value,
            'cadence_effective_from' => $view->cadenceEffectiveFrom()?->toDateString(),
            'current_period' => self::optional($view->current),
            'period_status' => $view->stateValue()?->value,
            'period_request' => self::requestOf($view->periodRequest()),
            'next_period' => self::upcoming($view->next, $view->settings->noticeDays),
            'next_submission_from' => $view->nextSubmissionFrom()?->toDateString(),
        ];
    }

    private static function attributionOf(Assessment $assessment): PeriodAttribution
    {
        return match (true) {
            $assessment->period_id !== null => PeriodAttribution::Assigned,
            $assessment->submitted_at !== null => PeriodAttribution::PrePeriodic,
            $assessment->status instanceof AccreditedAssessmentStatus && $assessment->status->isOpen() => PeriodAttribution::Pending,
            default => PeriodAttribution::None,
        };
    }

    private static function labelOf(PeriodAttribution $attribution, ?AssessmentPeriod $period): ?LocalizedText
    {
        if ($period instanceof AssessmentPeriod) {
            return $period->label();
        }

        $key = $attribution->labelKey();

        return $key === null ? null : LocalizedText::fromTranslation($key);
    }

    /**
     * @return array{id: int, number: string, status: string}|null
     */
    private static function requestOf(?Assessment $assessment): ?array
    {
        return $assessment instanceof Assessment ? ['id' => $assessment->id, 'number' => $assessment->request_number, 'status' => (string) $assessment->status->value] : null;
    }
}
