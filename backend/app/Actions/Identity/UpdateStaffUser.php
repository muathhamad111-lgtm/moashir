<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Audit\AuditEntry;
use App\Data\Identity\NationalIdEntry;
use App\Data\Identity\UserProfileChanges;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\Gender;
use App\Enums\Identity\IdentityChangeSource;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Identity\NationalIdAssigner;
use App\Services\Identity\RoleAssignmentGuard;
use App\Support\ValueObjects\NationalId;
use Illuminate\Support\Facades\DB;

final readonly class UpdateStaffUser
{
    public function __construct(
        private RoleAssignmentGuard $roleGuard,
        private SyncUserRoles $syncRoles,
        private NationalIdAssigner $nationalIds,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, User $target, UserProfileChanges $changes): User
    {
        $roles = $changes->roles === null ? null : $this->roleGuard->approve($actor, $target, $changes->roles);

        return DB::transaction(function () use ($actor, $target, $changes, $roles): User {
            $this->saveProfile($target, $changes);

            // An administrator sets the number only while none is recorded; changing one takes a change request.
            if ($changes->nationalId instanceof NationalId) {
                $this->nationalIds->assignFirst($target, $changes->nationalId, new NationalIdEntry($actor, IdentityChangeSource::AdminEntry));
            }

            if ($roles !== null) {
                $this->syncRoles->handle($actor, $target, $roles);
            }

            return $target->load('roles');
        });
    }

    private function saveProfile(User $target, UserProfileChanges $changes): void
    {
        $target->fill(array_filter([
            'name' => $changes->name,
            'email' => $changes->email?->value,
            'phone' => $changes->phone?->value,
            'gender' => $changes->gender,
        ], static fn (string|Gender|null $value): bool => $value !== null));

        if ($target->isDirty('email')) {
            $target->forceFill(['email_verified_at' => null]);
        }

        $genderChanged = $target->isDirty('gender');
        $target->save();

        if ($genderChanged) {
            $this->audit->record(new AuditEntry(AuditEvent::UserGenderRecorded, $target, [], ['by' => 'administrator']));
        }
    }
}
