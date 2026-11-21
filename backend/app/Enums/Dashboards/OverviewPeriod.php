<?php

declare(strict_types=1);

namespace App\Enums\Dashboards;

enum OverviewPeriod: string
{
    case Month = 'month';
    case Quarter = 'quarter';
    case Year = 'year';
    case Custom = 'custom';
}
