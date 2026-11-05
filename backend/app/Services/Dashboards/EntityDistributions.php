<?php

declare(strict_types=1);

namespace App\Services\Dashboards;

use App\Enums\Entities\EntityStatus;
use App\Models\Entities\Entity;
use App\Models\Entities\Region;
use App\Support\Dashboards\AggregateRow;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Builder;

final class EntityDistributions
{
    private const array GOVERNANCE_BANDS = [
        'below_50' => ['governance_grade < 50', []],
        'from_50_to_69' => ['governance_grade >= 50 and governance_grade < 70', []],
        'from_70_to_84' => ['governance_grade >= 70 and governance_grade < 85', []],
        'from_85_to_100' => ['governance_grade >= 85', []],
        'not_reported' => ['governance_grade is null', []],
    ];

    /**
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        return AggregateRow::counts($this->entities(), [
            'total' => ['true', []],
            'active' => ['status = ?', [EntityStatus::Active->value]],
            'pending_verification' => ['status = ?', [EntityStatus::PendingVerification->value]],
            'deactivated' => ['status = ?', [EntityStatus::Deactivated->value]],
        ]);
    }

    /**
     * @return list<array{region_id: int, code: string, name: array<string, string>, count: int}>
     */
    public function byRegion(): array
    {
        $counts = $this->entities()->toBase()->selectRaw('region_id, count(*) as total')->groupBy('region_id')->pluck('total', 'region_id');

        return array_values(Region::query()->orderBy('sort_order')->orderBy('id')->get(['id', 'code', 'name'])
            ->map(static fn (Region $region): array => [
                'region_id' => $region->id,
                'code' => $region->code,
                'name' => $region->name->toArray(),
                'count' => ScalarValue::int($counts->get($region->id)),
            ])
            ->all());
    }

    /**
     * @return list<array{key: string, count: int}>
     */
    public function bySector(): array
    {
        return $this->grouped('development_sector');
    }

    /**
     * @return list<array{key: string, count: int}>
     */
    public function byFoundingDecade(): array
    {
        return $this->grouped('(founding_year / 10) * 10');
    }

    /**
     * @return list<array{key: string, count: int}>
     */
    public function byGovernance(): array
    {
        $counts = AggregateRow::counts($this->entities(), self::GOVERNANCE_BANDS);

        return array_map(static fn (string $band): array => ['key' => $band, 'count' => $counts[$band] ?? 0], array_keys(self::GOVERNANCE_BANDS));
    }

    /**
     * @param  literal-string  $expression
     * @return list<array{key: string, count: int}>
     */
    private function grouped(string $expression): array
    {
        return array_values($this->entities()->toBase()
            ->selectRaw($expression.' as bucket, count(*) as total')
            ->groupByRaw($expression)
            ->orderByRaw($expression)
            ->get()
            ->map(static fn (object $row): array => [
                'key' => ScalarValue::string(get_object_vars($row)['bucket'] ?? null),
                'count' => ScalarValue::int(get_object_vars($row)['total'] ?? null),
            ])
            ->all());
    }

    /**
     * @return Builder<Entity>
     */
    private function entities(): Builder
    {
        return Entity::query()->notErased();
    }
}
