<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Overview;

use App\Data\Dashboards\BoardFilter;
use App\Models\Entities\Entity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final readonly class EntityPopulation
{
    /**
     * @return Builder<Entity>
     */
    public function current(BoardFilter $filter): Builder
    {
        $entities = Entity::query()->notErased();
        $filter->applyToEntities($entities);

        return $entities;
    }

    /**
     * @return Builder<Entity>
     */
    public function asAt(CarbonImmutable $moment, BoardFilter $filter): Builder
    {
        $at = $moment->toIso8601String();
        $entities = Entity::withTrashed()
            ->where('entities.created_at', '<', $at)
            ->where(static fn (Builder $kept): Builder => $kept->whereNull('entities.deleted_at')->orWhere('entities.deleted_at', '>=', $at))
            ->where(static fn (Builder $kept): Builder => $kept->whereNull('entities.anonymized_at')->orWhere('entities.anonymized_at', '>=', $at));
        $filter->applyToEntities($entities);

        return $entities;
    }
}
