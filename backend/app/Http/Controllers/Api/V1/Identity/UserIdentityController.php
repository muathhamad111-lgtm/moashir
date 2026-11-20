<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\ListUserIdentityHistory;
use App\Actions\Identity\SubmitIdentityChange;
use App\Http\Requests\Identity\SubmitNameCorrectionRequest;
use App\Http\Resources\Identity\IdentityChangeRequestResource;
use App\Http\Resources\Identity\IdentityHistoryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class UserIdentityController
{
    public function history(User $managedUser, ListUserIdentityHistory $history): JsonResponse
    {
        return ApiResponse::paginated($history->handle($managedUser), IdentityHistoryResource::class);
    }

    public function correctName(SubmitNameCorrectionRequest $request, User $managedUser, #[CurrentUser] User $actor, SubmitIdentityChange $submit): JsonResponse
    {
        $change = $submit->handle($actor, $managedUser, $request->draft())->loadMissing(['submitter', 'reviewer']);

        return ApiResponse::resource(new IdentityChangeRequestResource($change), Response::HTTP_CREATED);
    }
}
