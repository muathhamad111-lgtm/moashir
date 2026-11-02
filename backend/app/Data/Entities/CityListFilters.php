<?php

declare(strict_types=1);

namespace App\Data\Entities;

use App\Support\ValueObjects\SearchTerm;

final readonly class CityListFilters
{
    public function __construct(
        public ?int $regionId = null,
        public ?bool $active = null,
        public ?SearchTerm $search = null,
        public int $perPage = 50,
    ) {}
}
