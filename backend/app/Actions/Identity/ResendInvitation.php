<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\UserStatus;
use App\Exceptions\IllegalStateTransition;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class ResendInvitation
{
    public function __construct(private IssueInvitation $issueInvitation, private AuditRecorder $audit) {}

    public function handle(User $actor, User $invitee): void
    {
        if (! in_array($invitee->status, [UserStatus::Invited, UserStatus::Expired], true)) {
            throw IllegalStateTransition::from($invitee->effectiveStatus()->value, 'resend_invitation');
        }

        DB::transaction(function () use ($actor, $invitee): void {
            $invitee->forceFill(['status' => UserStatus::Invited])->save();
            $this->issueInvitation->handle($actor, $invitee);
            $this->audit->record(new AuditEntry(AuditEvent::UserReinvited, $invitee));
        });
    }
}
