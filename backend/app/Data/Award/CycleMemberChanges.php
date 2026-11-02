<?php

declare(strict_types=1);

namespace App\Data\Award;

use App\Enums\Award\CycleMemberRole;

final readonly class CycleMemberChanges
{
    /**
     * @param  array<string, list<int>>  $userIdsByRole
     */
    public function __construct(public array $userIdsByRole) {}

    /**
     * @return list<CycleMemberRole>
     */
    public function roles(): array
    {
        return array_values(array_filter(CycleMemberRole::cases(), fn (CycleMemberRole $role): bool => array_key_exists($role->value, $this->userIdsByRole)));
    }

    /**
     * @return list<int>
     */
    public function userIdsFor(CycleMemberRole $role): array
    {
        return $this->userIdsByRole[$role->value] ?? [];
    }
}
