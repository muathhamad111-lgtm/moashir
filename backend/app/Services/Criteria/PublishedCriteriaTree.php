<?php

declare(strict_types=1);

namespace App\Services\Criteria;

use App\Models\Criteria\CriteriaVersion;
use Illuminate\Contracts\Cache\Repository;

final readonly class PublishedCriteriaTree
{
    public const string CACHE_KEY = 'criteria:published-tree';

    public function __construct(
        private Repository $cache,
        private CriteriaTreeLoader $loader,
        private CriteriaTreeSerializer $serializer,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function current(): ?array
    {
        $tree = $this->cache->rememberForever(self::CACHE_KEY, $this->build(...));

        return is_array($tree) ? $tree : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function of(CriteriaVersion $version): array
    {
        return $this->serializer->serialize($this->loader->load($version));
    }

    public function forget(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function build(): ?array
    {
        $version = CriteriaVersion::query()->published()->first();

        return $version instanceof CriteriaVersion ? $this->of($version) : null;
    }
}
