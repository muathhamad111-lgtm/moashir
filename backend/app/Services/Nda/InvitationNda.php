<?php

declare(strict_types=1);

namespace App\Services\Nda;

use App\Models\Identity\TermsVersion;
use App\Models\Identity\UserInvitation;
use App\Services\Identity\UsableInvitationFinder;

/**
 * The agreement version an invitee must accept while accepting their invitation: the current one when the roles
 * they were invited with are in scope, otherwise none.
 */
final readonly class InvitationNda
{
    public function __construct(
        private UsableInvitationFinder $invitations,
        private NdaAcceptanceCheck $check,
        private NdaScope $scope,
    ) {}

    public function forToken(string $token): ?TermsVersion
    {
        $invitation = $this->invitations->tryFind($token);

        return $invitation instanceof UserInvitation ? $this->forInvitation($invitation) : null;
    }

    public function forInvitation(UserInvitation $invitation): ?TermsVersion
    {
        $current = $this->check->current();

        return $current instanceof TermsVersion && $this->scope->covers($invitation->user) ? $current : null;
    }
}
