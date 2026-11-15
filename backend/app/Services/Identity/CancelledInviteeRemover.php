<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Models\Identity\User;

/**
 * Removes an account that never accepted its invitation. The row is soft deleted (other records may
 * still point at it), its personal data is replaced by a pseudonym and its address is released so the
 * person can be invited again.
 */
final class CancelledInviteeRemover
{
    private const string PSEUDONYM_PREFIX = 'cancelled-invitation-';

    private const string RELEASED_DOMAIN = '@cancelled.invalid';

    public function remove(User $invitee): void
    {
        $pseudonym = self::PSEUDONYM_PREFIX.$invitee->id;

        User::withoutAuditing(static function () use ($invitee, $pseudonym): void {
            $invitee->syncRoles([]);
            $invitee->assessorQualifications()->delete();
            $invitee->requestPaths()->delete();
            $invitee->forceFill([
                'name' => $pseudonym,
                'email' => $pseudonym.self::RELEASED_DOMAIN,
                'phone' => null,
                'phone_verified_at' => null,
            ])->save();
            $invitee->delete();
        });
    }
}
