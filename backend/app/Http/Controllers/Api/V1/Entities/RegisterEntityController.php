<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\RegisterEntity;
use App\Http\Requests\Entities\RegisterEntityRequest;
use App\Http\Responses\Identity\LoginOutcomeResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class RegisterEntityController
{
    public function __invoke(RegisterEntityRequest $request, RegisterEntity $registerEntity, LoginOutcomeResponder $responder): JsonResponse
    {
        $outcome = $registerEntity->handle($request->registration(), $request->documents(), $request->emailVerificationToken());

        return $responder->respond($outcome)->setStatusCode(Response::HTTP_CREATED);
    }
}
