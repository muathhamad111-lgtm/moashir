<?php

declare(strict_types=1);

namespace App\Services\Periods;

use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Exceptions\Periods\PeriodRejected;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentPeriod;
use App\Support\Periods\LocalDay;
use Carbon\CarbonInterface;

final readonly class PeriodAssigner
{
    public function __construct(
        private PeriodicSettingsReader $settings,
        private PeriodLocator $periods,
        private PeriodSchedule $schedule,
    ) {}

    public function assign(Assessment $assessment, CarbonInterface $submittedAt): void
    {
        $period = $this->periodAt($submittedAt);

        if (! $period instanceof AssessmentPeriod) {
            return;
        }

        $this->assertNotAssessed($assessment, $period);
        $assessment->forceFill(['period_id' => $period->id, 'period_assigned_at' => $submittedAt]);
    }

    private function periodAt(CarbonInterface $instant): ?AssessmentPeriod
    {
        if (! $this->settings->read()->isLiveOn(LocalDay::of($instant))) {
            return null;
        }

        $period = $this->periods->containing($instant);

        if ($period instanceof AssessmentPeriod) {
            return $period;
        }

        $this->schedule->sync();

        return $this->periods->containing($instant);
    }

    private function assertNotAssessed(Assessment $assessment, AssessmentPeriod $period): void
    {
        $assessed = Assessment::query()
            ->ownedBy($assessment->entity_id)
            ->where('period_id', $period->id)
            ->where('status', AccreditedAssessmentStatus::Completed)
            ->whereKeyNot($assessment->id)
            ->exists();

        if ($assessed) {
            throw PeriodRejected::alreadyAssessed($period, $this->periods->after($period), $this->settings->read()->noticeDays);
        }
    }
}
