<?php

declare(strict_types=1);

namespace App\Data\Identity;

use App\Enums\Identity\IdentityChangeStatus;

final readonly class IdentityChangeListFilters
{
    public function __construct(public ?IdentityChangeStatus $status, public int $perPage) {}
}
