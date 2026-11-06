<?php

declare(strict_types=1);

namespace App\Services\Dashboards;

use App\Models\Assessments\Assessment;
use App\Models\Entities\Region;
use App\Support\Dashboards\AggregateRow;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class CommitteeStandings
{
    private const array HISTOGRAM = [
        'from_90_to_100' => ['average.percent >= 90', []],
        'from_80_to_89' => ['average.percent >= 80 and average.percent < 90', []],
        'from_70_to_79' => ['average.percent >= 70 and average.percent < 80', []],
        'from_60_to_69' => ['average.percent >= 60 and average.percent < 70', []],
        'below_60' => ['average.percent < 60', []],
    ];

    /**
     * @param  Builder<Assessment>  $requests
     * @return list<array<string, mixed>>
     */
    public function top(Builder $requests, int $limit): array
    {
        return array_values((clone $requests)
            ->join('committee_averages as average', 'average.id', '=', 'assessments.committee_average_id')
            ->with('entity:id,name')
            ->orderByDesc('average.percent')
            ->orderBy('assessments.id')
            ->limit($limit)
            ->get(['assessments.id', 'assessments.request_number', 'assessments.entity_id', 'assessments.region_id', 'assessments.path', 'assessments.status', DB::raw('round(average.percent, 2) as average_percent')])
            ->map(static fn (Assessment $assessment): array => [
                'assessment_id' => $assessment->id,
                'number' => $assessment->request_number,
                'entity_name' => $assessment->entity->name->toArray(),
                'region_id' => $assessment->region_id,
                'status' => (string) $assessment->status->value,
                'average_percent' => ScalarValue::string($assessment->getAttribute('average_percent')),
            ])
            ->all());
    }

    /**
     * @param  Builder<Assessment>  $requests
     * @return list<array{key: string, count: int}>
     */
    public function histogram(Builder $requests): array
    {
        $counts = AggregateRow::counts((clone $requests)->join('committee_averages as average', 'average.id', '=', 'assessments.committee_average_id'), self::HISTOGRAM);

        return array_map(static fn (string $bin): array => ['key' => $bin, 'count' => $counts[$bin] ?? 0], array_keys(self::HISTOGRAM));
    }

    /**
     * @param  Builder<Assessment>  $requests
     * @return list<array<string, mixed>>
     */
    public function byRegion(Builder $requests): array
    {
        $counts = (clone $requests)->toBase()->selectRaw('region_id, count(*) as total')->whereNotNull('region_id')->groupBy('region_id')->pluck('total', 'region_id');

        if ($counts->isEmpty()) {
            return [];
        }

        return array_values(Region::query()->whereKey($counts->keys()->all())->orderBy('sort_order')->get(['id', 'code', 'name'])
            ->map(static fn (Region $region): array => [
                'region_id' => $region->id,
                'code' => $region->code,
                'name' => $region->name->toArray(),
                'count' => ScalarValue::int($counts->get($region->id)),
            ])
            ->all());
    }
}
