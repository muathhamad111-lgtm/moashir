<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\ResendRegistrationEmailCode;
use App\Http\Requests\Identity\ResendOtpRequest;
use App\Http\Resources\Identity\OtpChallengeResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ResendRegistrationEmailCodeController
{
    public function __invoke(ResendOtpRequest $request, ResendRegistrationEmailCode $resendCode): JsonResponse
    {
        return ApiResponse::resource(new OtpChallengeResource($resendCode->handle($request->challengeId())));
    }
}
