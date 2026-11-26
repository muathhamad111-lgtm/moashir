<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Breakdowns;

use App\Contracts\Dashboards\OverviewBreakdownSource;
use App\Data\Dashboards\BoardFilter;
use App\Data\Dashboards\ReportingPeriod;
use App\Enums\Dashboards\OverviewBreakdownKey;
use App\Enums\Entities\EntityType;
use App\Enums\Entities\OrgSize;
use App\Models\Entities\Entity;
use App\Models\Entities\Region;
use App\Services\Dashboards\Overview\EntityPopulation;
use App\Support\ScalarValue;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;

final readonly class EntityProfileBreakdown implements OverviewBreakdownSource
{
    public function __construct(private EntityPopulation $population) {}

    public function keys(): array
    {
        return [OverviewBreakdownKey::EntitiesByType, OverviewBreakdownKey::EntitiesBySize, OverviewBreakdownKey::EntitiesByRegion];
    }

    /**
     * @return array{entities_by_type: list<array{key: string, count: int}>, entities_by_size: list<array{key: string, count: int}>, entities_by_region: list<array{key: string, name: array<string, string>, count: int}>}
     */
    public function breakdowns(ReportingPeriod $period, BoardFilter $filter): array
    {
        $entities = $this->population->asAt($period->end, $filter);

        return [
            OverviewBreakdownKey::EntitiesByType->value => $this->byEnum($entities, 'entity_type', EntityType::cases()),
            OverviewBreakdownKey::EntitiesBySize->value => $this->byEnum($entities, 'org_size', OrgSize::cases()),
            OverviewBreakdownKey::EntitiesByRegion->value => $this->byRegion($entities),
        ];
    }

    /**
     * @param  Builder<Entity>  $entities
     * @param  literal-string  $column
     * @param  list<BackedEnum>  $cases
     * @return list<array{key: string, count: int}>
     */
    private function byEnum(Builder $entities, string $column, array $cases): array
    {
        $counts = array_fill_keys(array_map(static fn (BackedEnum $case): string => (string) $case->value, $cases), 0);
        $rows = (clone $entities)->toBase()->reorder()->whereNotNull('entities.'.$column)
            ->selectRaw('entities.'.$column.' as bucket, count(*) as total')
            ->groupBy('entities.'.$column)
            ->get();

        foreach ($rows as $row) {
            $values = get_object_vars($row);
            $bucket = ScalarValue::string($values['bucket'] ?? null);

            if (array_key_exists($bucket, $counts)) {
                $counts[$bucket] += ScalarValue::int($values['total'] ?? null);
            }
        }

        return array_map(static fn (string $key, int $count): array => ['key' => $key, 'count' => $count], array_keys($counts), array_values($counts));
    }

    /**
     * @param  Builder<Entity>  $entities
     * @return list<array{key: string, name: array<string, string>, count: int}>
     */
    private function byRegion(Builder $entities): array
    {
        $counts = (clone $entities)->toBase()->reorder()->selectRaw('entities.region_id, count(*) as total')->groupBy('entities.region_id');

        return array_values(Region::query()
            ->joinSub($counts, 'counts', 'counts.region_id', '=', 'regions.id')
            ->orderByDesc('counts.total')->orderBy('regions.sort_order')->orderBy('regions.id')
            ->get(['regions.id', 'regions.code', 'regions.name', 'counts.total'])
            ->map(static fn (Region $region): array => [
                'key' => $region->code,
                'name' => $region->name->toArray(),
                'count' => ScalarValue::int($region->getAttribute('total')),
            ])
            ->all());
    }
}
