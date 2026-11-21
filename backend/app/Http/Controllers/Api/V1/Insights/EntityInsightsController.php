<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Insights;

use App\Actions\Insights\GetInsightsOverview;
use App\Actions\Insights\ListInsightsResults;
use App\Actions\Insights\ListReportLibrary;
use App\Http\Requests\Insights\IndexInsightsPageRequest;
use App\Http\Resources\Insights\InsightsOverviewResource;
use App\Http\Resources\Insights\ReportLibraryRowResource;
use App\Http\Resources\Insights\ResultRefResource;
use App\Http\Responses\ApiResponse;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Support\Insights\ReportLibraryPresenter;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class EntityInsightsController
{
    public function overview(Entity $entity, #[CurrentUser] User $viewer, GetInsightsOverview $overview): JsonResponse
    {
        return ApiResponse::resource(new InsightsOverviewResource($overview->handle($viewer, $entity)));
    }

    public function results(IndexInsightsPageRequest $request, Entity $entity, #[CurrentUser] User $viewer, ListInsightsResults $results): JsonResponse
    {
        return ApiResponse::paginated($results->handle($viewer, $entity, $request->page()), ResultRefResource::class);
    }

    public function library(IndexInsightsPageRequest $request, Entity $entity, #[CurrentUser] User $viewer, ListReportLibrary $library): JsonResponse
    {
        return ApiResponse::paginated($library->handle($viewer, $entity, $request->page()), ReportLibraryRowResource::class, [
            'coming_soon' => ReportLibraryPresenter::comingSoon(),
        ]);
    }
}
