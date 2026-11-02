<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Contracts\Search\SearchProvider;
use App\Data\Identity\UserSearchHit;
use App\Data\Search\SearchQuery;
use App\Data\Search\SearchResultGroup;
use App\Enums\Search\SearchResultType;
use App\Models\Identity\User;

final class UserSearchProvider implements SearchProvider
{
    public function type(): SearchResultType
    {
        return SearchResultType::Users;
    }

    public function isAvailableTo(User $actor): bool
    {
        return $actor->can('viewAny', User::class);
    }

    public function search(User $actor, SearchQuery $query): SearchResultGroup
    {
        $matches = User::query()->withoutSuperAdmins()->staff()->matchingSearch($query->term);

        $hits = (clone $matches)
            ->with('roles')
            ->orderBy('name')
            ->orderBy('id')
            ->limit($query->limit)
            ->get()
            ->map(static fn (User $user): UserSearchHit => UserSearchHit::fromUser($user))
            ->all();

        return new SearchResultGroup($this->type(), $matches->count(), array_values($hits));
    }
}
