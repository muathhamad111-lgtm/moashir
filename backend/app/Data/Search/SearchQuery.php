<?php

declare(strict_types=1);

namespace App\Data\Search;

use App\Support\ValueObjects\SearchTerm;

final readonly class SearchQuery
{
    public const int DEFAULT_LIMIT = 5;

    public const int MAX_LIMIT = 10;

    public function __construct(
        public SearchTerm $term,
        public int $limit = self::DEFAULT_LIMIT,
    ) {}
}
