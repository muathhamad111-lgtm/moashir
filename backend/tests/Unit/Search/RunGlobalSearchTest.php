<?php

declare(strict_types=1);

use App\Actions\Search\RunGlobalSearch;
use App\Contracts\Search\SearchProvider;
use App\Data\Search\SearchQuery;
use App\Data\Search\SearchResultGroup;
use App\Enums\Search\SearchResultType;
use App\Models\Identity\User;
use App\Services\Search\SearchProviderRegistry;
use App\Support\ValueObjects\SearchTerm;

function stubSearchProvider(SearchResultType $type, bool $available, int $total): SearchProvider
{
    return new readonly class($type, $available, $total) implements SearchProvider
    {
        public function __construct(private SearchResultType $resultType, private bool $available, private int $total) {}

        public function type(): SearchResultType
        {
            return $this->resultType;
        }

        public function isAvailableTo(User $actor): bool
        {
            return $this->available;
        }

        public function search(User $actor, SearchQuery $query): SearchResultGroup
        {
            return new SearchResultGroup($this->resultType, $this->total, []);
        }
    };
}

it('runs only the providers available to the actor, in registration order', function (): void {
    $registry = new SearchProviderRegistry([
        stubSearchProvider(SearchResultType::Entities, true, 4),
        stubSearchProvider(SearchResultType::Requests, false, 9),
        stubSearchProvider(SearchResultType::Users, true, 2),
    ]);

    $groups = new RunGlobalSearch($registry)->handle(new User, new SearchQuery(new SearchTerm('نورة')));

    expect(array_map(static fn (SearchResultGroup $group): array => [$group->type, $group->total], $groups))
        ->toBe([[SearchResultType::Entities, 4], [SearchResultType::Users, 2]])
        ->and($registry->hasAnyAvailableTo(new User))->toBeTrue();
});

it('reports when no provider is available', function (): void {
    $registry = new SearchProviderRegistry([stubSearchProvider(SearchResultType::Users, false, 1)]);

    expect($registry->hasAnyAvailableTo(new User))->toBeFalse()
        ->and($registry->availableTo(new User))->toBe([]);
});

it('registers the entities, requests and users providers in that order', function (): void {
    $types = array_map(
        static fn (SearchProvider $provider): SearchResultType => $provider->type(),
        app(SearchProviderRegistry::class)->availableTo(superAdmin()),
    );

    expect($types)->toBe([SearchResultType::Entities, SearchResultType::Requests, SearchResultType::Users]);
});
