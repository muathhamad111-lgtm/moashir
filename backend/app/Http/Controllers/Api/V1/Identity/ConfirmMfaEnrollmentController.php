<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\ConfirmMfaEnrollment;
use App\Data\Identity\LoginOutcome;
use App\Http\Requests\Identity\ConfirmMfaRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Identity\LoginOutcomeResponder;
use App\Models\Identity\User;
use Illuminate\Http\JsonResponse;

final class ConfirmMfaEnrollmentController
{
    public function __invoke(ConfirmMfaRequest $request, ConfirmMfaEnrollment $confirmEnrollment, LoginOutcomeResponder $responder): JsonResponse
    {
        $confirmation = $confirmEnrollment->handle($request->code());

        return $confirmation->signedInUser instanceof User
            ? $responder->respond(LoginOutcome::authenticated($confirmation->signedInUser), ['recovery_codes' => $confirmation->recoveryCodes])
            : ApiResponse::data(['recovery_codes' => $confirmation->recoveryCodes]);
    }
}
