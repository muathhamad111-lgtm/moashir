<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Enums\Identity\InvitationStatus;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use App\Models\Identity\UserInvitation;
use App\Notifications\Identity\StaffInvitationNotification;
use App\Services\Identity\AuthSettings;
use App\Services\Identity\SecretHasher;
use App\Services\Nda\InvitationNda;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

final readonly class IssueInvitation
{
    private const int TOKEN_LENGTH = 64;

    public function __construct(private AuthSettings $settings, private SecretHasher $hasher, private InvitationNda $nda) {}

    public function handle(User $actor, User $invitee): UserInvitation
    {
        $invitee->invitations()
            ->where('status', InvitationStatus::Pending)
            ->get()
            ->each(static fn (UserInvitation $previous): bool => $previous->forceFill(['status' => InvitationStatus::Revoked])->save());

        $token = Str::random(self::TOKEN_LENGTH);
        $validMinutes = $this->settings->invitationTtlMinutes();

        $invitation = $invitee->invitations()->create([
            'token_hash' => $this->hasher->hash($token),
            'status' => InvitationStatus::Pending,
            'invited_by_user_id' => $actor->id,
            'expires_at' => Date::now()->addMinutes($validMinutes),
        ]);

        $invitation->setRelation('user', $invitee);
        $invitee->notify(new StaffInvitationNotification($token, $validMinutes, $this->nda->forInvitation($invitation) instanceof TermsVersion));

        return $invitation;
    }
}
