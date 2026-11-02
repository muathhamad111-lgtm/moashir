<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\RegisterEntity;
use App\Enums\Identity\LoginStep;
use App\Http\Requests\Entities\RegisterEntityRequest;
use App\Http\Resources\Identity\OtpChallengeResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class RegisterEntityController
{
    public function __invoke(RegisterEntityRequest $request, RegisterEntity $registerEntity): JsonResponse
    {
        $challenge = $registerEntity->handle($request->registration());

        return ApiResponse::data([
            'step' => LoginStep::OtpRequired->value,
            'requires_otp' => true,
            'otp' => new OtpChallengeResource($challenge)->resolve($request),
            'message' => __('messages.registration_otp_sent'),
        ], Response::HTTP_CREATED);
    }
}
