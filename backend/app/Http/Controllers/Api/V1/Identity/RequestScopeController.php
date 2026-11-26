<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\SetRequestPathScope;
use App\Http\Requests\Identity\UpdateRequestScopeRequest;
use App\Http\Resources\Identity\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class RequestScopeController
{
    public function __invoke(UpdateRequestScopeRequest $request, User $managedUser, #[CurrentUser] User $actor, SetRequestPathScope $setScope): JsonResponse
    {
        $updated = $setScope->handle($actor, $managedUser, $request->paths(), $request->reason());

        return ApiResponse::resource(new UserResource($updated->load(User::ADMINISTRATION_RELATIONS)));
    }
}
