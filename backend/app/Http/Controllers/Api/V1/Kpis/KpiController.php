<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Kpis;

use App\Actions\Kpis\ArchiveKpi;
use App\Actions\Kpis\CreateKpi;
use App\Actions\Kpis\ListKpis;
use App\Actions\Kpis\SummarizeKpis;
use App\Actions\Kpis\UpdateKpi;
use App\Http\Requests\Kpis\IndexAdminKpisRequest;
use App\Http\Requests\Kpis\StoreKpiRequest;
use App\Http\Requests\Kpis\UpdateKpiRequest;
use App\Http\Resources\Kpis\KpiResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Models\Kpis\Kpi;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class KpiController
{
    public function index(IndexAdminKpisRequest $request, ListKpis $list, SummarizeKpis $summarize): JsonResponse
    {
        return ApiResponse::paginated($list->handle($request->filters(), app()->getLocale()), KpiResource::class, ['stats' => $summarize->handle()]);
    }

    public function store(StoreKpiRequest $request, #[CurrentUser] User $actor, CreateKpi $create): JsonResponse
    {
        $kpi = $create->handle($actor, $request->changes());

        return ApiResponse::resource(new KpiResource($kpi->refresh()->load(['image', 'updatedBy'])), Response::HTTP_CREATED);
    }

    public function show(Kpi $kpi): JsonResponse
    {
        return ApiResponse::resource(new KpiResource($kpi->load(['image', 'updatedBy'])));
    }

    public function update(UpdateKpiRequest $request, Kpi $kpi, #[CurrentUser] User $actor, UpdateKpi $update): JsonResponse
    {
        $update->handle($actor, $kpi, $request->changes());

        return ApiResponse::resource(new KpiResource($kpi->refresh()->load(['image', 'updatedBy'])));
    }

    public function destroy(Kpi $kpi, #[CurrentUser] User $actor, ArchiveKpi $archive): Response
    {
        $archive->handle($actor, $kpi);

        return ApiResponse::noContent();
    }
}
