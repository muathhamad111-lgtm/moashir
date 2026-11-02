<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\RequestSignInOtp;
use App\Data\Identity\LoginOutcome;
use App\Http\Requests\Identity\RequestOtpRequest;
use App\Http\Responses\Identity\LoginOutcomeResponder;
use Illuminate\Http\JsonResponse;

final class RequestOtpController
{
    public function __invoke(RequestOtpRequest $request, RequestSignInOtp $requestOtp, LoginOutcomeResponder $responder): JsonResponse
    {
        return $responder->respond(LoginOutcome::otpRequired($requestOtp->handle($request->email(), $request->channel())));
    }
}
