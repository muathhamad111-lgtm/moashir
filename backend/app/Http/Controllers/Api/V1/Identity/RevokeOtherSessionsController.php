<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\RevokeOtherSessions;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class RevokeOtherSessionsController
{
    public function __invoke(#[CurrentUser] User $user, RevokeOtherSessions $revokeOthers): JsonResponse
    {
        return ApiResponse::data(['revoked' => $revokeOthers->handle($user)]);
    }
}
