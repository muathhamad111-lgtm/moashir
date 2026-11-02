<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\VerifySignInOtp;
use App\Http\Requests\Identity\VerifyOtpRequest;
use App\Http\Responses\Identity\LoginOutcomeResponder;
use Illuminate\Http\JsonResponse;

final class VerifyOtpController
{
    public function __invoke(VerifyOtpRequest $request, VerifySignInOtp $verifyOtp, LoginOutcomeResponder $responder): JsonResponse
    {
        return $responder->respond($verifyOtp->handle($request->challengeId(), $request->code()));
    }
}
