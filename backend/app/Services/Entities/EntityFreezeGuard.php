<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Exceptions\Entities\EntityRejected;
use App\Models\Entities\Entity;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class EntityFreezeGuard
{
    public function assertNotFrozen(Entity $entity): void
    {
        if ($entity->trashed()) {
            throw EntityRejected::deleted();
        }
    }

    public function assertEntityNotFrozen(int $entityId): void
    {
        if ($this->isFrozen($entityId)) {
            throw EntityRejected::deleted();
        }
    }

    public function isFrozen(int $entityId): bool
    {
        return Entity::onlyTrashed()->whereKey($entityId)->exists();
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function excludeFrozen(Builder $query, string $entityColumn = 'entity_id'): Builder
    {
        return $query->whereIn($entityColumn, static fn (QueryBuilder $operational): QueryBuilder => $operational
            ->select('id')
            ->from('entities')
            ->whereNull('deleted_at'));
    }
}
