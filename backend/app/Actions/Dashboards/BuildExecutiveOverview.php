<?php

declare(strict_types=1);

namespace App\Actions\Dashboards;

use App\Data\Dashboards\BoardFilter;
use App\Data\Dashboards\ExecutiveOverview;
use App\Data\Dashboards\ReportingPeriod;
use App\Models\Identity\User;
use App\Services\Dashboards\Breakdowns\OverviewBreakdowns;
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
        private OverviewBreakdowns $breakdowns,
    ) {}

    public function handle(ReportingPeriod $period, BoardFilter $filter, User $viewer): ExecutiveOverview
    {
        return new ExecutiveOverview(
            $period,
            $this->activity->figures($period, $filter),
            $this->activity->figures($period->previous(), $filter),
            $this->trends->of($period, $filter),
            $this->snapshot->activeEntities($filter),
            $this->snapshot->widgets($period, $filter),
            $this->geography->of($period, $filter),
            $this->breakdowns->visibleTo($viewer, $period, $filter),
            Date::now(),
        );
    }
}
