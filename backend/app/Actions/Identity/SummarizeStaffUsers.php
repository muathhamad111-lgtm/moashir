<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Identity\UserListFilters;
use App\Enums\Identity\RoleKey;
use App\Enums\Identity\UserStatus;

final readonly class SummarizeStaffUsers
{
    public function __construct(private ListStaffUsers $listUsers) {}

    /**
     * @return array{total: int, active: int, locked: int, suspended: int, invited: int, by_role: array<string, int>}
     */
    public function handle(UserListFilters $filters): array
    {
        $count = fn (?UserStatus $status): int => $this->listUsers
            ->filteredQuery(new UserListFilters($filters->search, $filters->role, $status))
            ->count();

        return [
            'total' => $count(null),
            'active' => $count(UserStatus::Active),
            'locked' => $count(UserStatus::Locked),
            'suspended' => $count(UserStatus::Suspended),
            'invited' => $count(UserStatus::Invited),
            'by_role' => $this->countByRole(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function countByRole(): array
    {
        $counts = [];

        foreach (RoleKey::cases() as $role) {
            if ($role->isStaff()) {
                $counts[$role->value] = $this->listUsers->filteredQuery(new UserListFilters(role: $role))->count();
            }
        }

        return $counts;
    }
}
