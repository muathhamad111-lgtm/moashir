<?php

declare(strict_types=1);

namespace App\Data\Periods;

use App\Enums\Periods\PeriodTimeline;

final readonly class PeriodListFilters
{
    public function __construct(public ?PeriodTimeline $timeline = null, public int $perPage = 20) {}
}
