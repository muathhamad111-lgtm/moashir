<?php

declare(strict_types=1);

namespace App\Data\Award;

use App\Enums\Award\AwardCycleStatus;
use App\Support\ValueObjects\SearchTerm;

final readonly class CycleListFilters
{
    public function __construct(
        public ?AwardCycleStatus $status = null,
        public ?SearchTerm $search = null,
        public string $sort = '-submission_start',
        public int $perPage = 15,
    ) {}

    public function withoutStatus(): self
    {
        return new self(null, $this->search, $this->sort, $this->perPage);
    }
}
