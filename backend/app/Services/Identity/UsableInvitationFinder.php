<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Enums\Identity\InvitationStatus;
use App\Exceptions\Identity\AccountActionRejected;
use App\Models\Identity\UserInvitation;
use Illuminate\Database\Eloquent\Builder;

final readonly class UsableInvitationFinder
{
    public function __construct(private SecretHasher $hasher) {}

    public function find(string $token): UserInvitation
    {
        return $this->usable($this->byToken($token)->first());
    }

    public function findForUpdate(string $token): UserInvitation
    {
        return $this->usable($this->byToken($token)->lockForUpdate()->first());
    }

    /**
     * @return Builder<UserInvitation>
     */
    private function byToken(string $token): Builder
    {
        return UserInvitation::query()->where('token_hash', $this->hasher->hash($token));
    }

    private function usable(?UserInvitation $invitation): UserInvitation
    {
        if (! $invitation instanceof UserInvitation || in_array($invitation->status, [InvitationStatus::Accepted, InvitationStatus::Revoked], true)) {
            throw AccountActionRejected::invitationInvalid();
        }

        if (! $invitation->isUsable()) {
            throw AccountActionRejected::invitationExpired();
        }

        return $invitation;
    }
}
