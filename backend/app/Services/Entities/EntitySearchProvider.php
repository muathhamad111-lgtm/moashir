<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Contracts\Search\SearchProvider;
use App\Data\Entities\EntitySearchHit;
use App\Data\Search\SearchQuery;
use App\Data\Search\SearchResultGroup;
use App\Enums\Identity\PermissionKey;
use App\Enums\Search\SearchResultType;
use App\Models\Entities\Entity;
use App\Models\Identity\User;

final readonly class EntitySearchProvider implements SearchProvider
{
    public function __construct(private EntityRecordScope $scope) {}

    public function type(): SearchResultType
    {
        return SearchResultType::Entities;
    }

    public function isAvailableTo(User $actor): bool
    {
        return $actor->can(PermissionKey::EntitiesView->value);
    }

    public function search(User $actor, SearchQuery $query): SearchResultGroup
    {
        $matches = Entity::query()->notErased()->matchingSearch($query->term);
        $this->scope->restrict($matches, $actor);

        $hits = (clone $matches)
            ->orderByRaw("name->>'ar' asc")
            ->orderBy('id')
            ->limit($query->limit)
            ->get()
            ->map(static fn (Entity $entity): EntitySearchHit => EntitySearchHit::fromEntity($entity))
            ->all();

        return new SearchResultGroup($this->type(), $matches->count(), array_values($hits));
    }
}
