<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Nda;

use App\Http\Responses\ApiResponse;
use App\Services\Nda\NdaImpact;
use Illuminate\Http\JsonResponse;

final class NdaImpactController
{
    public function __invoke(NdaImpact $impact): JsonResponse
    {
        return ApiResponse::data($impact->ofNewVersion());
    }
}
