<?php

declare(strict_types=1);

namespace App\Contracts\Dashboards;

use App\Data\Dashboards\BoardFilter;
use App\Data\Dashboards\ReportingPeriod;
use App\Enums\Dashboards\OverviewBreakdownKey;

interface OverviewBreakdownSource
{
    /**
     * @return list<OverviewBreakdownKey>
     */
    public function keys(): array;

    /**
     * @return array<string, mixed> keyed by the OverviewBreakdownKey values of keys()
     */
    public function breakdowns(ReportingPeriod $period, BoardFilter $filter): array;
}
