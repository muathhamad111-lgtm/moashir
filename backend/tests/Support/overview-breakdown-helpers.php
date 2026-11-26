<?php

declare(strict_types=1);

use App\Contracts\Dashboards\OverviewBreakdownSource;
use App\Data\Dashboards\BoardFilter;
use App\Data\Dashboards\ReportingPeriod;
use App\Enums\Dashboards\OverviewPeriod;
use App\Models\Entities\Entity;
use App\Models\Entities\Region;
use App\Services\Dashboards\Overview\ReportingPeriods;

function overviewMonth(): ReportingPeriod
{
    return app(ReportingPeriods::class)->preset(OverviewPeriod::Month);
}

/**
 * @param  class-string<OverviewBreakdownSource>  $source
 */
function breakdownOf(string $source, string $key, ?BoardFilter $filter = null, ?ReportingPeriod $period = null): mixed
{
    return app($source)->breakdowns($period ?? overviewMonth(), $filter ?? new BoardFilter)[$key];
}

/**
 * @param  array<string, mixed>  $attributes
 */
function profiledEntity(array $attributes = [], ?Region $region = null): Entity
{
    return entityWithOwner(static fn ($factory) => $factory->state([...$attributes, ...($region instanceof Region ? ['region_id' => $region->id] : [])]));
}
