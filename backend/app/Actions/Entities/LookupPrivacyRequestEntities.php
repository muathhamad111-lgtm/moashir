<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Entities\EntityLookupQuery;
use App\Models\Entities\Entity;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class LookupPrivacyRequestEntities
{
    /**
     * @return Collection<int, Entity>
     */
    public function handle(EntityLookupQuery $query): Collection
    {
        return Entity::withTrashed()
            ->notErased()
            ->when($query->term, static fn (Builder $matches, SearchTerm $term): Builder => $matches->matchingLookup($term))
            ->orderByRaw("name->>'ar' asc")
            ->orderBy('id')
            ->limit($query->limit)
            ->get();
    }
}
