<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Criteria;

use App\Http\Resources\Criteria\CriteriaTreeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Criteria\CriteriaVersion;
use App\Services\Criteria\CriteriaTreeLoader;
use Illuminate\Http\JsonResponse;

final class CriteriaTreeController
{
    public function __invoke(CriteriaVersion $version, CriteriaTreeLoader $loader): JsonResponse
    {
        return ApiResponse::resource(new CriteriaTreeResource($loader->load($version)));
    }
}
