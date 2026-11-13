<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Kpis;

use App\Actions\Kpis\MoveKpi;
use App\Actions\Kpis\ReorderKpis;
use App\Http\Requests\Cms\ReorderCmsContentRequest;
use App\Http\Requests\Kpis\MoveKpiRequest;
use App\Http\Resources\Kpis\KpiOrderResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Models\Kpis\Kpi;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class KpiOrderController
{
    public function move(MoveKpiRequest $request, Kpi $kpi, #[CurrentUser] User $actor, MoveKpi $move): JsonResponse
    {
        $move->handle($actor, $kpi, $request->direction());

        return $this->orderedList($request);
    }

    public function reorder(ReorderCmsContentRequest $request, #[CurrentUser] User $actor, ReorderKpis $reorder): JsonResponse
    {
        $reorder->handle($actor, $request->orderedIds());

        return $this->orderedList($request);
    }

    private function orderedList(Request $request): JsonResponse
    {
        return ApiResponse::data(KpiOrderResource::collection(Kpi::query()->ordered()->get())->resolve($request));
    }
}
