<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\ResetForgottenPassword;
use App\Http\Requests\Identity\ResetPasswordRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ResetPasswordController
{
    public function __invoke(ResetPasswordRequest $request, ResetForgottenPassword $resetPassword): JsonResponse
    {
        $resetPassword->handle($request->email(), $request->token(), $request->password());

        return ApiResponse::data(['message' => __('passwords.reset')]);
    }
}
