<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\SendRegistrationEmailCode;
use App\Http\Requests\Entities\RegistrationEmailCodeRequest;
use App\Http\Resources\Identity\OtpChallengeResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class RegistrationEmailCodeController
{
    public function __invoke(RegistrationEmailCodeRequest $request, SendRegistrationEmailCode $sendCode): JsonResponse
    {
        return ApiResponse::resource(new OtpChallengeResource($sendCode->handle($request->officialEmail())));
    }
}
