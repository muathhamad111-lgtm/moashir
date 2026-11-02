<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\PermissionKey;
use App\Exceptions\Identity\PermissionMatrixRejected;
use App\Models\Identity\Role;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class UpdateRolePermissions
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  list<string>  $requestedKeys
     */
    public function handle(User $actor, Role $role, array $requestedKeys): Role
    {
        $this->assertEditable($actor, $role);

        $requested = $this->knownKeys(array_values(array_unique($requestedKeys)));
        $before = $role->permissionKeys();

        $this->assertLockedKeysUntouched($role, $requested, $before);

        DB::transaction(function () use ($role, $requested, $before): void {
            $role->syncPermissions($requested);
            $role->unsetRelation('permissions');
            $after = $role->permissionKeys();

            if ($before !== $after) {
                $this->audit->record(new AuditEntry(AuditEvent::RolePermissionsChanged, $role, ['permissions' => $before], ['permissions' => $after]));
            }
        });

        return $role->load('permissions');
    }

    private function assertEditable(User $actor, Role $role): void
    {
        if (! $role->key()->isAssignable()) {
            throw PermissionMatrixRejected::roleNotEditable();
        }

        if (in_array($role->key(), $actor->roleKeys(), true)) {
            throw PermissionMatrixRejected::selfGrant();
        }
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function knownKeys(array $keys): array
    {
        $unknown = array_values(array_diff($keys, PermissionKey::values()));

        if ($unknown !== []) {
            throw PermissionMatrixRejected::unknownKeys($unknown);
        }

        return $keys;
    }

    /**
     * @param  list<string>  $requested
     * @param  list<string>  $current
     */
    private function assertLockedKeysUntouched(Role $role, array $requested, array $current): void
    {
        $violations = [];

        foreach (PermissionKey::cases() as $permission) {
            if (! $permission->isLocked()) {
                continue;
            }

            $isLockedHere = in_array($role->key(), $permission->lockedToRoles(), true);
            $wasHeld = in_array($permission->value, $current, true);
            $isRequested = in_array($permission->value, $requested, true);

            if ($isRequested !== ($isLockedHere && $wasHeld)) {
                $violations[] = $permission->value;
            }
        }

        if ($violations !== []) {
            throw PermissionMatrixRejected::lockedKeys($violations);
        }
    }
}
