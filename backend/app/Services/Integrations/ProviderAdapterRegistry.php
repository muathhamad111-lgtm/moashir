<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Contracts\Integrations\ServesProvider;
use App\Enums\Integrations\IntegrationProvider;
use Illuminate\Container\Attributes\Tag;
use LogicException;

final readonly class ProviderAdapterRegistry
{
    public const string TAG = 'integrations.adapters';

    /**
     * @param  iterable<ServesProvider>  $adapters
     */
    public function __construct(#[Tag(self::TAG)] private iterable $adapters) {}

    /**
     * @template TAdapter of ServesProvider
     *
     * @param  class-string<TAdapter>  $contract
     * @return TAdapter
     */
    public function get(string $contract, IntegrationProvider $provider): ServesProvider
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter instanceof $contract && $adapter->provider() === $provider) {
                return $adapter;
            }
        }

        throw new LogicException(sprintf('No %s adapter is registered for %s.', $contract, $provider->value));
    }
}
