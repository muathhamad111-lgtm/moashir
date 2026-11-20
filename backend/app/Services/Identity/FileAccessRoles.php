<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Models\Identity\Role;
use Illuminate\Database\Eloquent\Builder;

final readonly class FileAccessRoles
{
    private const array FILE_ACCESS_PERMISSIONS = [
        PermissionKey::EvidenceView,
        PermissionKey::EvidenceManage,
        PermissionKey::FinanceTransfersReview,
        PermissionKey::FinanceOperationsView,
        PermissionKey::FinanceRefundsManage,
        PermissionKey::PaymentsOwnView,
    ];

    /**
     * @return list<RoleKey>
     */
    public function all(): array
    {
        $names = Role::query()
            ->whereHas('permissions', static fn (Builder $query): Builder => $query->whereIn('name', self::permissionNames()))
            ->pluck('name')
            ->all();

        return array_values(array_filter(RoleKey::cases(), static fn (RoleKey $role): bool => in_array($role->value, $names, true)));
    }

    /**
     * @return list<string>
     */
    private static function permissionNames(): array
    {
        return array_map(static fn (PermissionKey $key): string => $key->value, self::FILE_ACCESS_PERMISSIONS);
    }
}
