<?php

declare(strict_types=1);

namespace App\Data\Entities;

use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;

final readonly class StaffAudience
{
    /**
     * @param  non-empty-list<RoleKey>  $roles
     * @param  list<PermissionKey>  $holding
     * @param  list<PermissionKey>  $lacking
     */
    public function __construct(public array $roles, public array $holding = [], public array $lacking = []) {}

    /**
     * @return list<string>
     */
    public function roleValues(): array
    {
        return array_map(static fn (RoleKey $role): string => $role->value, $this->roles);
    }
}
