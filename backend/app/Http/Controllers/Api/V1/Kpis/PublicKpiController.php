<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Kpis;

use App\Actions\Kpis\ListFeaturedKpis;
use App\Actions\Kpis\ListKpis;
use App\Http\Requests\Kpis\IndexKpisRequest;
use App\Http\Resources\Kpis\KpiPublicDetailResource;
use App\Http\Resources\Kpis\KpiPublicResource;
use App\Http\Responses\ApiResponse;
use App\Models\Kpis\Kpi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicKpiController
{
    public function index(IndexKpisRequest $request, ListKpis $list): JsonResponse
    {
        return ApiResponse::paginated($list->handle($request->filters(), app()->getLocale()), KpiPublicResource::class);
    }

    public function featured(Request $request, ListFeaturedKpis $featured): JsonResponse
    {
        $kpis = $featured->handle();

        return ApiResponse::data(KpiPublicResource::collection($kpis)->resolve($request), meta: [
            'minimum' => config()->integer('moashir.kpis.featured_min'),
            'count' => $kpis->count(),
        ]);
    }

    public function show(Kpi $publishedKpi): JsonResponse
    {
        return ApiResponse::resource(new KpiPublicDetailResource($publishedKpi->load(['category', 'type', 'image', 'values'])));
    }
}
