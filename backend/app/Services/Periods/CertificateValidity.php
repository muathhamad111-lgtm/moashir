<?php

declare(strict_types=1);

namespace App\Services\Periods;

use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentPeriod;
use App\Support\Periods\LocalDay;
use App\Support\Periods\PeriodCalendar;
use App\Support\ValueObjects\PeriodWindow;
use Carbon\CarbonImmutable;

final readonly class CertificateValidity
{
    public function __construct(private PeriodicSettingsReader $settings, private PeriodLocator $periods) {}

    public function validUntil(Assessment $assessment): ?CarbonImmutable
    {
        if (! $assessment->isAccredited()) {
            return null;
        }

        $period = $assessment->period_id === null ? null : $assessment->period;

        return $period instanceof AssessmentPeriod ? $this->endOfPeriodAfter($period) : $this->endOfFirstPeriod();
    }

    private function endOfPeriodAfter(AssessmentPeriod $period): CarbonImmutable
    {
        $next = $this->periods->after($period);

        if ($next instanceof AssessmentPeriod) {
            return $next->ends_on;
        }

        $nextStart = LocalDay::parse($period->ends_on->toDateString())->addDay();

        return PeriodWindow::containing($this->settings->read()->cadence, $nextStart)->endsOn;
    }

    private function endOfFirstPeriod(): CarbonImmutable
    {
        $settings = $this->settings->read();
        $rollout = $settings->rolloutFrom ?? LocalDay::today();
        $first = $this->periods->startingOn($rollout);

        return $first instanceof AssessmentPeriod ? $first->ends_on : PeriodCalendar::startingOn($settings->cadence, $rollout)->endsOn;
    }
}
