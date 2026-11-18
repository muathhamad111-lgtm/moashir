<?php

declare(strict_types=1);

namespace App\Data\Award;

use App\Enums\Award\CommitteeAssignmentStatus;
use App\Support\ListOrder;

final readonly class CommitteeAssignmentFilters
{
    public function __construct(
        public ?int $cycleId,
        public ?CommitteeAssignmentStatus $status,
        public ListOrder $order,
        public int $perPage,
    ) {}
}
