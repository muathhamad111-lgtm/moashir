<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Kpis;

use App\Actions\Kpis\CreateKpiType;
use App\Actions\Kpis\RemoveKpiType;
use App\Actions\Kpis\UpdateKpiType;
use App\Http\Requests\Kpis\KpiTypeRequest;
use App\Http\Resources\Kpis\KpiTypeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Kpis\KpiType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class KpiTypeController
{
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::data(KpiTypeResource::collection(KpiType::query()->listed()->get())->resolve($request));
    }

    public function store(KpiTypeRequest $request, CreateKpiType $create): JsonResponse
    {
        return ApiResponse::resource(new KpiTypeResource($create->handle($request->entry())), Response::HTTP_CREATED);
    }

    public function update(KpiTypeRequest $request, KpiType $type, UpdateKpiType $update): JsonResponse
    {
        $update->handle($type, $request->entry());

        return ApiResponse::resource(new KpiTypeResource($type->refresh()));
    }

    public function destroy(KpiType $type, RemoveKpiType $remove): Response
    {
        $remove->handle($type);

        return ApiResponse::noContent();
    }
}
