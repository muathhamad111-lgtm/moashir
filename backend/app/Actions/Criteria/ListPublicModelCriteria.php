<?php

declare(strict_types=1);

namespace App\Actions\Criteria;

use App\Services\Criteria\PublishedCriteriaTree;

final readonly class ListPublicModelCriteria
{
    private const array PUBLIC_FIELDS = ['id', 'code', 'order', 'group', 'name', 'description'];

    public function __construct(private PublishedCriteriaTree $tree) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function handle(): array
    {
        $mains = $this->tree->current()['mains'] ?? [];

        if (! is_array($mains)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $main): array => is_array($main) ? array_intersect_key($main, array_flip(self::PUBLIC_FIELDS)) : [],
            $mains,
        ));
    }
}
