<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\AttemptPasswordSignIn;
use App\Actions\Identity\RequestSignInOtp;
use App\Data\Identity\LoginOutcome;
use App\Enums\Identity\LoginMode;
use App\Http\Requests\Identity\LoginRequest;
use App\Http\Responses\Identity\LoginOutcomeResponder;
use Illuminate\Http\JsonResponse;

final class LoginController
{
    public function __invoke(
        LoginRequest $request,
        AttemptPasswordSignIn $passwordSignIn,
        RequestSignInOtp $requestOtp,
        LoginOutcomeResponder $responder,
    ): JsonResponse {
        $outcome = $request->mode() === LoginMode::Password
            ? $passwordSignIn->handle($request->email(), $request->password())
            : LoginOutcome::otpRequired($requestOtp->handle($request->email(), $request->channel()));

        return $responder->respond($outcome);
    }
}
