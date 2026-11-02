<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\ResendInvitation;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class ResendInvitationController
{
    public function __invoke(User $managedUser, #[CurrentUser] User $actor, ResendInvitation $resendInvitation): JsonResponse
    {
        $resendInvitation->handle($actor, $managedUser);

        return ApiResponse::data(null, Response::HTTP_ACCEPTED);
    }
}
