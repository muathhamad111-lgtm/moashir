<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Breakdowns;

use App\Contracts\Dashboards\OverviewBreakdownSource;
use App\Data\Dashboards\BoardFilter;
use App\Data\Dashboards\ReportingPeriod;
use App\Enums\Dashboards\OverviewBreakdownKey;
use App\Models\Kpis\Kpi;
use App\Models\Kpis\KpiValue;
use App\Support\ValueObjects\KpiNumber;

final readonly class KpiTargetBreakdown implements OverviewBreakdownSource
{
    private const int MAX_KPIS = 6;

    private const string LATEST_VALUE = 'latest_value';

    public function keys(): array
    {
        return [OverviewBreakdownKey::KpiTargets];
    }

    /**
     * @return array{kpi_targets: list<array{key: string, name: array{ar: string, en: string}, value: int|float|null, target: int|float|null}>}
     */
    public function breakdowns(ReportingPeriod $period, BoardFilter $filter): array
    {
        $latestValue = KpiValue::query()->select('value')
            ->whereColumn('kpi_values.kpi_id', 'kpis.id')
            ->where('measured_on', '<=', $period->lastDay()->toDateString())
            ->orderByDesc('measured_on')->orderByDesc('id')
            ->limit(1);

        $kpis = Kpi::query()->published()->whereNotNull('target')->ordered()->limit(self::MAX_KPIS)
            ->select(['id', 'slug', 'name', 'value', 'target'])
            ->addSelect([self::LATEST_VALUE => $latestValue])
            ->get();

        return [OverviewBreakdownKey::KpiTargets->value => array_values($kpis->map(static fn (Kpi $kpi): array => [
            'key' => $kpi->slug,
            'name' => $kpi->name->toArray(),
            'value' => (KpiNumber::tryFrom($kpi->getAttribute(self::LATEST_VALUE)) ?? $kpi->value)?->toNumber(),
            'target' => $kpi->target?->toNumber(),
        ])->all())];
    }
}
