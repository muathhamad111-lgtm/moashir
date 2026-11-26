<?php

declare(strict_types=1);

namespace App\Data\Entities;

use App\Enums\Entities\ProfileChangeStatus;
use App\Support\ValueObjects\SearchTerm;

final readonly class ProfileChangeListFilters
{
    public function __construct(
        public ?ProfileChangeStatus $status,
        public ?int $entityId,
        public ?SearchTerm $search,
        public int $perPage,
    ) {}
}
