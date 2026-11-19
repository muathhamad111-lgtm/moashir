<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Services\Identity\UsableInvitationFinder;
use Illuminate\Http\JsonResponse;

final class ShowInvitationController
{
    public function __invoke(string $token, UsableInvitationFinder $invitations): JsonResponse
    {
        $invitations->find($token);

        return response()->json(['data' => ['usable' => true]]);
    }
}
