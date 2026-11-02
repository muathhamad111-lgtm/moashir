<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\UnlockUser;
use App\Http\Requests\Identity\OptionalReasonRequest;
use App\Http\Resources\Identity\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Http\JsonResponse;

final class UnlockUserController
{
    public function __invoke(OptionalReasonRequest $request, User $managedUser, UnlockUser $unlockUser): JsonResponse
    {
        return ApiResponse::resource(new UserResource($unlockUser->handle($managedUser, $request->reason())->load('roles')));
    }
}
