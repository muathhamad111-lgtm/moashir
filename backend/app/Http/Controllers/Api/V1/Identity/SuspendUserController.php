<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\SuspendUser;
use App\Http\Requests\Identity\RequiredReasonRequest;
use App\Http\Resources\Identity\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class SuspendUserController
{
    public function __invoke(RequiredReasonRequest $request, User $managedUser, #[CurrentUser] User $actor, SuspendUser $suspendUser): JsonResponse
    {
        return ApiResponse::resource(new UserResource($suspendUser->handle($actor, $managedUser, $request->reason())->load(User::ADMINISTRATION_RELATIONS)));
    }
}
