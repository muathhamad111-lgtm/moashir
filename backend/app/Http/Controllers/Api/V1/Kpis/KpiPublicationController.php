<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Kpis;

use App\Actions\Kpis\SetKpiPublished;
use App\Http\Requests\Kpis\SetKpiPublishedRequest;
use App\Http\Resources\Kpis\KpiResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Models\Kpis\Kpi;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class KpiPublicationController
{
    public function __invoke(SetKpiPublishedRequest $request, Kpi $kpi, #[CurrentUser] User $actor, SetKpiPublished $setPublished): JsonResponse
    {
        $setPublished->handle($actor, $kpi, $request->published());

        return ApiResponse::resource(new KpiResource($kpi->refresh()->load(['image', 'updatedBy'])));
    }
}
