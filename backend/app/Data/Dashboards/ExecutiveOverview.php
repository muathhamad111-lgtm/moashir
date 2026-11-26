<?php

declare(strict_types=1);

namespace App\Data\Dashboards;

use Carbon\CarbonInterface;

final readonly class ExecutiveOverview
{
    /**
     * @param  list<DashboardWidget>  $snapshot  the entities as at the period's end and the period's weakest criteria
     * @param  array{regions: list<array<string, mixed>>, cities: list<array<string, mixed>>}  $geography  the map figures per region and city
     * @param  array<string, mixed>  $breakdowns  the breakdowns the viewer may see, keyed by OverviewBreakdownKey value
     */
    public function __construct(
        public ReportingPeriod $period,
        public PeriodFigures $current,
        public PeriodFigures $previous,
        public TrendSeries $trends,
        public int $activeEntities,
        public array $snapshot,
        public array $geography,
        public array $breakdowns,
        public CarbonInterface $generatedAt,
    ) {}
}
