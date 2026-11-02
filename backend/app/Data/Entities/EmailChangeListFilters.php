<?php

declare(strict_types=1);

namespace App\Data\Entities;

use App\Enums\Identity\EmailChangeStatus;

final readonly class EmailChangeListFilters
{
    public function __construct(
        public ?EmailChangeStatus $status = EmailChangeStatus::AwaitingApproval,
        public int $perPage = 15,
    ) {}
}
