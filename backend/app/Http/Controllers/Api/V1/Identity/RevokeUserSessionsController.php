<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\RevokeUserSessions;
use App\Http\Requests\Identity\OptionalReasonRequest;
use App\Http\Resources\Identity\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class RevokeUserSessionsController
{
    public function __invoke(OptionalReasonRequest $request, User $managedUser, #[CurrentUser] User $actor, RevokeUserSessions $revokeSessions): JsonResponse
    {
        $user = $revokeSessions->handle($actor, $managedUser, $request->reason());

        return ApiResponse::resource(new UserResource($user->load(User::ADMINISTRATION_RELATIONS)));
    }
}
