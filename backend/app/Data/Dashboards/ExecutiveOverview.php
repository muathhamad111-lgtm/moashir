<?php

declare(strict_types=1);

namespace App\Data\Dashboards;

use Carbon\CarbonInterface;

final readonly class ExecutiveOverview
{
    /**
     * @param  list<DashboardWidget>  $snapshot  the platform as it stands now, whatever the period
     */
    public function __construct(
        public ReportingPeriod $period,
        public PeriodFigures $current,
        public PeriodFigures $previous,
        public TrendSeries $trends,
        public int $activeEntities,
        public array $snapshot,
        public CarbonInterface $generatedAt,
    ) {}
}
