<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Kpis;

use App\Actions\Kpis\ListActiveKpiCategories;
use App\Actions\Kpis\ListActiveKpiTypes;
use App\Http\Resources\Kpis\KpiCategoryResource;
use App\Http\Resources\Kpis\KpiTypeResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicKpiCatalogController
{
    public function categories(Request $request, ListActiveKpiCategories $categories): JsonResponse
    {
        return ApiResponse::data(KpiCategoryResource::collection($categories->handle())->resolve($request));
    }

    public function types(Request $request, ListActiveKpiTypes $types): JsonResponse
    {
        return ApiResponse::data(KpiTypeResource::collection($types->handle())->resolve($request));
    }
}
