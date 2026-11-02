<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\RequestSuperAdminEmailChange;
use App\Http\Requests\Identity\ChangeOwnEmailRequest;
use App\Http\Resources\Identity\OtpChallengeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class ChangeOwnEmailController
{
    public function __invoke(ChangeOwnEmailRequest $request, #[CurrentUser] User $user, RequestSuperAdminEmailChange $requestChange): JsonResponse
    {
        return ApiResponse::resource(new OtpChallengeResource($requestChange->handle($user, $request->newEmail())));
    }
}
