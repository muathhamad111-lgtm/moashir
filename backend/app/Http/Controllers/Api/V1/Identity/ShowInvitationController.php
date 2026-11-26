<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Models\Identity\TermsVersion;
use App\Services\Identity\UsableInvitationFinder;
use App\Services\Nda\InvitationNda;
use App\Services\Nda\NdaTextPresenter;
use App\Support\Identity\AccountNationalId;
use Illuminate\Http\JsonResponse;

final class ShowInvitationController
{
    /**
     * The invitee sees the national ID an administrator entered, masked, and may confirm or replace it, and the
     * non-disclosure agreement to accept when their roles are in its scope.
     */
    public function __invoke(string $token, UsableInvitationFinder $invitations, InvitationNda $nda, NdaTextPresenter $presenter): JsonResponse
    {
        $invitation = $invitations->find($token);
        $agreement = $nda->forInvitation($invitation);

        return response()->json(['data' => [
            'usable' => true,
            'national_id_masked' => AccountNationalId::of($invitation->user)?->masked(),
            'nda' => $agreement instanceof TermsVersion ? $presenter->present($agreement) : null,
        ]]);
    }
}
