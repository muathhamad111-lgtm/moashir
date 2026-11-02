<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class HealthController
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::data(['status' => 'ok', 'message' => __('messages.health_ok')]);
    }
}
