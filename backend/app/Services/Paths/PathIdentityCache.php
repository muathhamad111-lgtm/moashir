<?php

declare(strict_types=1);

namespace App\Services\Paths;

use Closure;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Repository;

/**
 * Keeps the stored path descriptions in the shared cache, memoised for the current request so a list of
 * rows resolves every path label from a single lookup.
 */
final readonly class PathIdentityCache
{
    private const string KEY = 'paths:identity';

    private const int SECONDS = 3600;

    public function __construct(private CacheManager $cache) {}

    /**
     * @param  Closure(): array<string, mixed>  $load
     * @return array<string, mixed>
     */
    public function remember(Closure $load): array
    {
        $cached = $this->store()->remember(self::KEY, self::SECONDS, static fn (): array => ['values' => $load()]);
        $values = is_array($cached) && is_array($cached['values'] ?? null) ? $cached['values'] : [];
        $stored = [];

        foreach ($values as $key => $value) {
            $stored[(string) $key] = $value;
        }

        return $stored;
    }

    public function forget(): void
    {
        $this->store()->forget(self::KEY);
    }

    private function store(): Repository
    {
        return $this->cache->memo();
    }
}
