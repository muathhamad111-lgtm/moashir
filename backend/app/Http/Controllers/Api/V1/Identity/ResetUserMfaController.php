<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\ResetUserMfa;
use App\Http\Requests\Identity\RequiredReasonRequest;
use App\Http\Resources\Identity\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class ResetUserMfaController
{
    public function __invoke(RequiredReasonRequest $request, User $managedUser, #[CurrentUser] User $actor, ResetUserMfa $resetMfa): JsonResponse
    {
        return ApiResponse::resource(new UserResource($resetMfa->handle($actor, $managedUser, $request->reason())->load(User::ADMINISTRATION_RELATIONS)));
    }
}
