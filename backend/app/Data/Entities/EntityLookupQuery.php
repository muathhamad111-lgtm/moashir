<?php

declare(strict_types=1);

namespace App\Data\Entities;

use App\Support\ValueObjects\SearchTerm;

final readonly class EntityLookupQuery
{
    public const int DEFAULT_LIMIT = 10;

    public const int MAX_LIMIT = 20;

    public function __construct(
        public ?SearchTerm $term,
        public int $limit = self::DEFAULT_LIMIT,
    ) {}
}
