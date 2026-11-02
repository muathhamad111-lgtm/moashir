<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\RequestEntityEmailChange;
use App\Http\Requests\Entities\RequestEmailChangeRequest;
use App\Http\Resources\Identity\OtpChallengeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class MyEmailChangeController
{
    public function __invoke(RequestEmailChangeRequest $request, #[CurrentUser] User $owner, RequestEntityEmailChange $requestChange): JsonResponse
    {
        $challenge = $requestChange->handle($owner, $request->newEmail());

        return ApiResponse::data([
            'otp' => new OtpChallengeResource($challenge)->resolve($request),
            'message' => __('messages.email_change_otp_sent'),
        ]);
    }
}
