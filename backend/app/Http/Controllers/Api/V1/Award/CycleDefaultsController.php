<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Award;

use App\Actions\Award\GetCycleDefaults;
use App\Http\Resources\Award\CycleDefaultsResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CycleDefaultsController
{
    public function __invoke(GetCycleDefaults $defaults): JsonResponse
    {
        return ApiResponse::resource(new CycleDefaultsResource($defaults->handle()));
    }
}
