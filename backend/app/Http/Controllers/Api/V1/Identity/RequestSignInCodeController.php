<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\RequestSignInCode;
use App\Data\Identity\LoginOutcome;
use App\Http\Requests\Identity\RequestSignInCodeRequest;
use App\Http\Responses\Identity\LoginOutcomeResponder;
use Illuminate\Http\JsonResponse;

final class RequestSignInCodeController
{
    public function __invoke(RequestSignInCodeRequest $request, RequestSignInCode $requestCode, LoginOutcomeResponder $responder): JsonResponse
    {
        return $responder->respond(
            LoginOutcome::otpRequired($requestCode->handle($request->identifier())),
            ['message' => __('messages.sign_in_code_sent')],
        );
    }
}
