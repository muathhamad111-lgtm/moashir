<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Kpis;

use App\Actions\Kpis\RecordKpiMeasurement;
use App\Http\Requests\Kpis\RecordKpiMeasurementRequest;
use App\Http\Resources\Kpis\KpiValueResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Models\Kpis\Kpi;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class KpiValueController
{
    public function index(Request $request, Kpi $kpi): JsonResponse
    {
        return ApiResponse::data(KpiValueResource::collection($kpi->values()->get())->resolve($request));
    }

    public function store(RecordKpiMeasurementRequest $request, Kpi $kpi, #[CurrentUser] User $actor, RecordKpiMeasurement $record): JsonResponse
    {
        $record->handle($actor, $kpi, $request->measurement());

        return ApiResponse::data(KpiValueResource::collection($kpi->values()->get())->resolve($request), Response::HTTP_CREATED);
    }
}
