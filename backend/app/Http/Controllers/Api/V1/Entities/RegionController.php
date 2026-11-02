<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\ListRegions;
use App\Http\Resources\Entities\RegionResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class RegionController
{
    public function index(ListRegions $listRegions): JsonResponse
    {
        return ApiResponse::data(RegionResource::collection($listRegions->handle())->resolve(request()));
    }
}
