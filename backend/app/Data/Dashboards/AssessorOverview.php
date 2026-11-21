<?php

declare(strict_types=1);

namespace App\Data\Dashboards;

use Carbon\CarbonInterface;

final readonly class AssessorOverview
{
    /**
     * @param  array<string, int>  $levels  results the assessor's reviews produced per level, in the period
     */
    public function __construct(
        public ReportingPeriod $period,
        public AssessorPeriodFigures $current,
        public AssessorPeriodFigures $previous,
        public TrendSeries $trends,
        public array $levels,
        public CarbonInterface $generatedAt,
    ) {}
}
