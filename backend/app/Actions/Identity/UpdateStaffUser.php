<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Identity\UserProfileChanges;
use App\Models\Identity\User;
use App\Services\Identity\RoleAssignmentGuard;
use Illuminate\Support\Facades\DB;

final readonly class UpdateStaffUser
{
    public function __construct(private RoleAssignmentGuard $roleGuard, private SyncUserRoles $syncRoles) {}

    public function handle(User $actor, User $target, UserProfileChanges $changes): User
    {
        $roles = $changes->roles === null ? null : $this->roleGuard->approve($actor, $target, $changes->roles);

        return DB::transaction(function () use ($actor, $target, $changes, $roles): User {
            $target->fill(array_filter([
                'name' => $changes->name,
                'email' => $changes->email?->value,
                'phone' => $changes->phone?->value,
            ], static fn (?string $value): bool => $value !== null));

            if ($target->isDirty('email')) {
                $target->forceFill(['email_verified_at' => null]);
            }

            $target->save();

            if ($roles !== null) {
                $this->syncRoles->handle($actor, $target, $roles);
            }

            return $target->load('roles');
        });
    }
}
