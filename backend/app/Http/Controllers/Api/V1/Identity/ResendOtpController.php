<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\ResendSignInOtp;
use App\Data\Identity\LoginOutcome;
use App\Http\Requests\Identity\ResendOtpRequest;
use App\Http\Responses\Identity\LoginOutcomeResponder;
use Illuminate\Http\JsonResponse;

final class ResendOtpController
{
    public function __invoke(ResendOtpRequest $request, ResendSignInOtp $resendOtp, LoginOutcomeResponder $responder): JsonResponse
    {
        return $responder->respond(LoginOutcome::otpRequired($resendOtp->handle($request->challengeId())));
    }
}
