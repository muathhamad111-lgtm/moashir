<?php

declare(strict_types=1);

namespace App\Actions\Search;

use App\Contracts\Search\SearchProvider;
use App\Data\Search\SearchQuery;
use App\Data\Search\SearchResultGroup;
use App\Models\Identity\User;
use App\Services\Search\SearchProviderRegistry;

final readonly class RunGlobalSearch
{
    public function __construct(private SearchProviderRegistry $registry) {}

    /**
     * @return list<SearchResultGroup>
     */
    public function handle(User $actor, SearchQuery $query): array
    {
        return array_map(
            static fn (SearchProvider $provider): SearchResultGroup => $provider->search($actor, $query),
            $this->registry->availableTo($actor),
        );
    }
}
