<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\VerifyMfaChallenge;
use App\Data\Identity\LoginOutcome;
use App\Http\Requests\Identity\MfaChallengeRequest;
use App\Http\Responses\Identity\LoginOutcomeResponder;
use Illuminate\Http\JsonResponse;

final class MfaChallengeController
{
    public function __invoke(MfaChallengeRequest $request, VerifyMfaChallenge $verifyMfa, LoginOutcomeResponder $responder): JsonResponse
    {
        return $responder->respond(LoginOutcome::authenticated($verifyMfa->handle($request->code(), $request->recoveryCode())));
    }
}
