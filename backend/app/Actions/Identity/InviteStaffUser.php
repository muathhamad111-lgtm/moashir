<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Audit\AuditEntry;
use App\Data\Identity\StaffUserData;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\UserStatus;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Identity\RoleAssignmentGuard;
use Illuminate\Support\Facades\DB;

final readonly class InviteStaffUser
{
    public function __construct(
        private RoleAssignmentGuard $roleGuard,
        private SyncUserRoles $syncRoles,
        private IssueInvitation $issueInvitation,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, StaffUserData $data): User
    {
        $roles = $this->roleGuard->approve($actor, null, $data->roles);

        return DB::transaction(function () use ($actor, $data, $roles): User {
            $user = new User([
                'name' => $data->name,
                'email' => $data->email->value,
                'phone' => $data->phone?->value,
                'locale' => $data->locale,
            ]);
            $user->forceFill(['status' => UserStatus::Invited, 'mfa_required' => true])->save();

            $this->syncRoles->handle($actor, $user, $roles);
            $this->issueInvitation->handle($actor, $user);
            $this->audit->record(new AuditEntry(AuditEvent::UserInvited, $user));

            return $user->load('roles');
        });
    }
}
