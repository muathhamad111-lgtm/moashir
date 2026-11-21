<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\VerifyRegistrationEmailCode;
use App\Http\Requests\Identity\VerifyOtpRequest;
use App\Http\Resources\Entities\EmailVerificationTokenResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class VerifyRegistrationEmailCodeController
{
    public function __invoke(VerifyOtpRequest $request, VerifyRegistrationEmailCode $verifyCode): JsonResponse
    {
        return ApiResponse::resource(new EmailVerificationTokenResource($verifyCode->handle($request->challengeId(), $request->code())));
    }
}
