<?php

declare(strict_types=1);

namespace App\Data\Identity;

use App\Enums\Identity\RoleKey;
use App\Enums\Identity\UserStatus;
use App\Support\ValueObjects\SearchTerm;

final readonly class UserListFilters
{
    public function __construct(
        public ?SearchTerm $search = null,
        public ?RoleKey $role = null,
        public ?UserStatus $status = null,
        public string $sort = 'name',
        public int $perPage = 15,
    ) {}
}
