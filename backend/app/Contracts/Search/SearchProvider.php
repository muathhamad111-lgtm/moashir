<?php

declare(strict_types=1);

namespace App\Contracts\Search;

use App\Data\Search\SearchQuery;
use App\Data\Search\SearchResultGroup;
use App\Enums\Search\SearchResultType;
use App\Models\Identity\User;

interface SearchProvider
{
    public function type(): SearchResultType;

    public function isAvailableTo(User $actor): bool;

    public function search(User $actor, SearchQuery $query): SearchResultGroup;
}
