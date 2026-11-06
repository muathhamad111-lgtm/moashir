<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Dashboards;

use App\Actions\Dashboards\BuildActionCenter;
use App\Actions\Dashboards\BuildDashboardSummary;
use App\Data\Dashboards\ActionItem;
use App\Http\Requests\Dashboards\DashboardSummaryRequest;
use App\Http\Resources\Dashboards\ActionItemResource;
use App\Http\Resources\Dashboards\DashboardSummaryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Date;

final class DashboardController
{
    public function summary(DashboardSummaryRequest $request, #[CurrentUser] User $user, BuildDashboardSummary $buildSummary): JsonResponse
    {
        return ApiResponse::resource(new DashboardSummaryResource($buildSummary->handle($user, $request->board())));
    }

    public function actionCenter(#[CurrentUser] User $user, BuildActionCenter $buildActionCenter): JsonResponse
    {
        $items = $buildActionCenter->handle($user);

        return ApiResponse::data(ActionItemResource::collection($items)->resolve(request()), meta: [
            'total' => array_sum(array_map(static fn (ActionItem $item): int => $item->count, $items)),
            'generated_at' => Date::now()->toIso8601String(),
        ]);
    }
}
