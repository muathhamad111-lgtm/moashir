<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\RegenerateRecoveryCodes;
use App\Http\Requests\Identity\CurrentPasswordRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class RecoveryCodesController
{
    public function __invoke(CurrentPasswordRequest $request, #[CurrentUser] User $user, RegenerateRecoveryCodes $regenerate): JsonResponse
    {
        return ApiResponse::data(['recovery_codes' => $regenerate->handle($user)]);
    }
}
