<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\AttemptPasswordSignIn;
use App\Http\Requests\Identity\LoginRequest;
use App\Http\Responses\Identity\LoginOutcomeResponder;
use Illuminate\Http\JsonResponse;

final class LoginController
{
    public function __invoke(LoginRequest $request, AttemptPasswordSignIn $signIn, LoginOutcomeResponder $responder): JsonResponse
    {
        return $responder->respond($signIn->handle($request->identifier(), $request->password()));
    }
}
