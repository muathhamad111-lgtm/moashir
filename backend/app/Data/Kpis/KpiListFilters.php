<?php

declare(strict_types=1);

namespace App\Data\Kpis;

use App\Enums\Kpis\KpiSort;
use App\Enums\Kpis\KpiStatus;
use App\Support\ValueObjects\SearchTerm;

final readonly class KpiListFilters
{
    public function __construct(
        public ?SearchTerm $search,
        public ?int $categoryId,
        public ?KpiStatus $status,
        public ?int $measurementYear,
        public ?int $typeId,
        public ?SearchTerm $owner,
        public ?bool $published,
        public ?bool $featured,
        public KpiSort $sort,
        public int $perPage,
    ) {}
}
