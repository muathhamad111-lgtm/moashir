<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\InvitationStatus;
use App\Enums\Identity\UserStatus;
use App\Exceptions\Identity\AccountActionRejected;
use App\Exceptions\IllegalStateTransition;
use App\Models\Identity\User;
use App\Models\Identity\UserInvitation;
use App\Services\Audit\AuditRecorder;
use App\Services\Identity\CancelledInviteeRemover;
use App\Support\ValueObjects\EmailAddress;
use Illuminate\Support\Facades\DB;

final readonly class CancelInvitation
{
    private const string VERB = 'cancel_invitation';

    public function __construct(private AuditRecorder $audit, private CancelledInviteeRemover $remover) {}

    public function handle(User $actor, User $invitee): void
    {
        if ($actor->is($invitee)) {
            throw AccountActionRejected::selfTarget();
        }

        DB::transaction(function () use ($invitee): void {
            $locked = User::query()->whereKey($invitee->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [UserStatus::Invited, UserStatus::Expired], true)) {
                throw IllegalStateTransition::from($locked->effectiveStatus()->value, self::VERB);
            }

            $this->audit->record(new AuditEntry(AuditEvent::UserInvitationCancelled, $locked, [
                'status' => $locked->status->value,
                'email' => new EmailAddress($locked->email)->masked(),
            ]));

            $locked->invitations()
                ->where('status', InvitationStatus::Pending)
                ->get()
                ->each(static fn (UserInvitation $invitation): bool => $invitation->forceFill(['status' => InvitationStatus::Revoked])->save());

            $this->remover->remove($locked);
        });
    }
}
