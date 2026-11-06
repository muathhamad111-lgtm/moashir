<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Kpis;

use App\Actions\Kpis\CreateKpiCategory;
use App\Actions\Kpis\RemoveKpiCategory;
use App\Actions\Kpis\UpdateKpiCategory;
use App\Http\Requests\Kpis\KpiCategoryRequest;
use App\Http\Resources\Kpis\KpiCategoryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Kpis\KpiCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class KpiCategoryController
{
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::data(KpiCategoryResource::collection(KpiCategory::query()->listed()->get())->resolve($request));
    }

    public function store(KpiCategoryRequest $request, CreateKpiCategory $create): JsonResponse
    {
        return ApiResponse::resource(new KpiCategoryResource($create->handle($request->entry())), Response::HTTP_CREATED);
    }

    public function update(KpiCategoryRequest $request, KpiCategory $category, UpdateKpiCategory $update): JsonResponse
    {
        $update->handle($category, $request->entry());

        return ApiResponse::resource(new KpiCategoryResource($category->refresh()));
    }

    public function destroy(KpiCategory $category, RemoveKpiCategory $remove): Response
    {
        $remove->handle($category);

        return ApiResponse::noContent();
    }
}
