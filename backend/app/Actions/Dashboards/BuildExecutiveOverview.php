<?php

declare(strict_types=1);

namespace App\Actions\Dashboards;

use App\Data\Dashboards\ExecutiveOverview;
use App\Data\Dashboards\ReportingPeriod;
use App\Services\Dashboards\Geography\GeographicActivity;
use App\Services\Dashboards\Overview\ActivityTrends;
use App\Services\Dashboards\Overview\PeriodActivity;
use App\Services\Dashboards\Overview\PlatformSnapshot;
use Illuminate\Support\Facades\Date;

final readonly class BuildExecutiveOverview
{
    public function __construct(
        private PeriodActivity $activity,
        private ActivityTrends $trends,
        private PlatformSnapshot $snapshot,
        private GeographicActivity $geography,
    ) {}

    public function handle(ReportingPeriod $period): ExecutiveOverview
    {
        return new ExecutiveOverview(
            $period,
            $this->activity->figures($period),
            $this->activity->figures($period->previous()),
            $this->trends->of($period),
            $this->snapshot->activeEntities(),
            $this->snapshot->widgets(),
            $this->geography->of($period),
            Date::now(),
        );
    }
}
