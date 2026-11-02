<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Contracts\Search\SearchProvider;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\Tag;

final readonly class SearchProviderRegistry
{
    public const string TAG = 'search.providers';

    /**
     * @var list<SearchProvider>
     */
    private array $providers;

    /**
     * @param  iterable<SearchProvider>  $providers
     */
    public function __construct(#[Tag(self::TAG)] iterable $providers)
    {
        $this->providers = iterator_to_array($providers, false);
    }

    /**
     * @return list<SearchProvider>
     */
    public function availableTo(User $actor): array
    {
        return array_values(array_filter(
            $this->providers,
            static fn (SearchProvider $provider): bool => $provider->isAvailableTo($actor),
        ));
    }

    public function hasAnyAvailableTo(User $actor): bool
    {
        return $this->availableTo($actor) !== [];
    }
}
