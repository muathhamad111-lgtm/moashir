<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Http\Resources\Identity\LoginModesResource;
use App\Http\Responses\ApiResponse;
use App\Services\Identity\LoginOptionsReader;
use Illuminate\Http\JsonResponse;

final class LoginModesController
{
    public function __invoke(LoginOptionsReader $options): JsonResponse
    {
        return ApiResponse::resource(new LoginModesResource($options->read()));
    }
}
