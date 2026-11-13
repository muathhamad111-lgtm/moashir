<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Data\Periods\PeriodicPreview;
use App\Data\Periods\PeriodicSettingsUpdate;
use App\Data\Periods\PlannedPeriod;
use App\Enums\Notifications\NotificationCode;
use App\Models\Assessments\AssessmentPeriod;
use App\Models\Assessments\PeriodNotice;
use App\Services\Periods\PeriodicSettingsReader;
use App\Services\Periods\PeriodLocator;
use App\Support\Periods\LocalDay;
use App\Support\Periods\PeriodPlanner;
use App\Support\ValueObjects\PeriodWindow;

final readonly class PreviewPeriodicSettings
{
    public function __construct(private PeriodicSettingsReader $settings, private PeriodLocator $periods) {}

    public function handle(PeriodicSettingsUpdate $update): PeriodicPreview
    {
        $today = LocalDay::today();
        $settings = $update->appliedTo($this->settings->read());
        $current = $this->periods->current();
        $lastStarted = $this->periods->lastStarted($today);
        $plan = PeriodPlanner::plan($settings, $today, $lastStarted?->window(LocalDay::timezone())->endsOn);
        $existing = array_values($this->periods->upcoming()->all());
        $removed = array_values(array_filter($existing, static fn (AssessmentPeriod $period): bool => self::matching($period->window(LocalDay::timezone()), $plan->upcoming) === null));

        return new PeriodicPreview(
            $settings,
            $current,
            $current instanceof AssessmentPeriod && $current->cadence !== $settings->cadence ? $current->ends_on->addDay() : null,
            array_map(static fn (PeriodWindow $window): PlannedPeriod => new PlannedPeriod($window, self::idOf($window, $existing)), $plan->upcoming),
            $removed,
            $this->correctionsFor($removed),
        );
    }

    /**
     * @param  list<AssessmentPeriod>  $removed
     */
    private function correctionsFor(array $removed): int
    {
        if ($removed === []) {
            return 0;
        }

        return PeriodNotice::query()
            ->whereIn('assessment_period_id', array_map(static fn (AssessmentPeriod $period): int => $period->id, $removed))
            ->where('code', NotificationCode::PeriodStartingSoon)
            ->count();
    }

    /**
     * @param  list<AssessmentPeriod>  $existing
     */
    private static function idOf(PeriodWindow $window, array $existing): ?int
    {
        foreach ($existing as $period) {
            if ($period->window(LocalDay::timezone())->sameAs($window)) {
                return $period->id;
            }
        }

        return null;
    }

    /**
     * @param  list<PeriodWindow>  $windows
     */
    private static function matching(PeriodWindow $window, array $windows): ?PeriodWindow
    {
        foreach ($windows as $candidate) {
            if ($candidate->sameAs($window)) {
                return $candidate;
            }
        }

        return null;
    }
}
