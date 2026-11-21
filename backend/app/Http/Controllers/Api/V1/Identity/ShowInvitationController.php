<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Services\Identity\UsableInvitationFinder;
use App\Support\Identity\AccountNationalId;
use Illuminate\Http\JsonResponse;

final class ShowInvitationController
{
    /**
     * The invitee sees the national ID an administrator entered, masked, and may confirm or replace it.
     */
    public function __invoke(string $token, UsableInvitationFinder $invitations): JsonResponse
    {
        $invitation = $invitations->find($token);

        return response()->json(['data' => ['usable' => true, 'national_id_masked' => AccountNationalId::of($invitation->user)?->masked()]]);
    }
}
