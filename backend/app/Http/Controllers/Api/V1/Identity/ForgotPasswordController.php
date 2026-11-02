<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\RequestPasswordReset;
use App\Http\Requests\Identity\ForgotPasswordRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ForgotPasswordController
{
    public function __invoke(ForgotPasswordRequest $request, RequestPasswordReset $requestReset): JsonResponse
    {
        $requestReset->handle($request->email());

        return ApiResponse::data(['message' => __('messages.password_reset_link_sent')]);
    }
}
