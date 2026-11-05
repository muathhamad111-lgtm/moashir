<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Kpis;

use App\Http\Resources\Kpis\KpiCategoryResource;
use App\Http\Resources\Kpis\KpiTypeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Kpis\KpiCategory;
use App\Models\Kpis\KpiType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicKpiCatalogController
{
    public function categories(Request $request): JsonResponse
    {
        return ApiResponse::data(KpiCategoryResource::collection(KpiCategory::query()->where('is_active', true)->listed()->get())->resolve($request));
    }

    public function types(Request $request): JsonResponse
    {
        return ApiResponse::data(KpiTypeResource::collection(KpiType::query()->where('is_active', true)->listed()->get())->resolve($request));
    }
}
