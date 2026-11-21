<?php

declare(strict_types=1);

namespace App\Data\Dashboards;

use Carbon\CarbonInterface;

final readonly class ExecutiveOverview
{
    /**
     * @param  list<DashboardWidget>  $snapshot  the platform as it stands now, whatever the period
     * @param  array{regions: list<array<string, mixed>>, cities: list<array<string, mixed>>}  $geography  the map figures per region and city
     */
    public function __construct(
        public ReportingPeriod $period,
        public PeriodFigures $current,
        public PeriodFigures $previous,
        public TrendSeries $trends,
        public int $activeEntities,
        public array $snapshot,
        public array $geography,
        public CarbonInterface $generatedAt,
    ) {}
}
