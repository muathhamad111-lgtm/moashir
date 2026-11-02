<?php

declare(strict_types=1);

namespace App\Data\Criteria;

use App\Support\ValueObjects\SearchTerm;

final readonly class MainCriteriaListQuery
{
    public function __construct(
        public ?int $versionId,
        public ?SearchTerm $search,
        public ?bool $active,
        public string $sort,
        public int $perPage,
    ) {}
}
