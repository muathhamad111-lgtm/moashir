<?php

declare(strict_types=1);

namespace App\Actions\Dashboards;

use App\Data\Dashboards\AssessorOverview;
use App\Data\Dashboards\ReportingPeriod;
use App\Data\Dashboards\TrendSeries;
use App\Models\Identity\User;
use App\Services\Dashboards\Assessor\AssessorLevels;
use App\Services\Dashboards\Assessor\AssessorPeriodActivity;
use App\Services\Dashboards\Assessor\AssessorReviews;
use App\Services\Dashboards\Overview\PeriodBuckets;
use Illuminate\Support\Facades\Date;

final readonly class BuildAssessorOverview
{
    public function __construct(
        private AssessorPeriodActivity $activity,
        private AssessorReviews $reviews,
        private AssessorLevels $levels,
        private PeriodBuckets $buckets,
    ) {}

    public function handle(User $assessor, ReportingPeriod $period): AssessorOverview
    {
        return new AssessorOverview(
            $period,
            $this->activity->figures($assessor, $period),
            $this->activity->figures($assessor, $period->previous()),
            new TrendSeries($period->bucket(), $this->buckets->keys($period), [
                'completed' => $this->buckets->counts($this->reviews->completed($assessor), 'ended_at', $period),
            ]),
            $this->levels->given($assessor, $period),
            Date::now(),
        );
    }
}
