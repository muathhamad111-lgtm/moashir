<?php

declare(strict_types=1);

namespace App\Services\Dashboards;

use App\Enums\Certificates\EntityLevel;
use App\Enums\Certificates\EntityLevelBucket;
use App\Enums\Entities\EntityStatus;
use App\Models\Entities\Entity;
use App\Models\Entities\Region;
use App\Support\Certificates\EntityLevelQuery;
use App\Support\Dashboards\AggregateRow;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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
     * @return list<array{key: string, count: int}>
     */
    public function byLevel(): array
    {
        $ranked = $this->entities()->select('entities.id');
        EntityLevelQuery::selectLevelKey($ranked);
        $ranked->withExists(EntityLevelQuery::openAccreditedRequestFlag());
        $counts = array_fill_keys(array_column(EntityLevelBucket::cases(), 'value'), 0);

        $rows = DB::query()->fromSub($ranked, 'ranked')
            ->selectRaw(EntityLevelQuery::LEVEL_KEY.', '.EntityLevelQuery::OPEN_ACCREDITED_REQUEST.', count(*) as total')
            ->groupBy([EntityLevelQuery::LEVEL_KEY, EntityLevelQuery::OPEN_ACCREDITED_REQUEST])
            ->get();

        foreach ($rows as $row) {
            $values = get_object_vars($row);
            $bucket = EntityLevelBucket::of(EntityLevel::tryFrom(ScalarValue::string($values[EntityLevelQuery::LEVEL_KEY] ?? null)), (bool) ($values[EntityLevelQuery::OPEN_ACCREDITED_REQUEST] ?? false));
            $counts[$bucket->value] += ScalarValue::int($values['total'] ?? null);
        }

        return array_map(static fn (string $key, int $count): array => ['key' => $key, 'count' => $count], array_keys($counts), array_values($counts));
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
