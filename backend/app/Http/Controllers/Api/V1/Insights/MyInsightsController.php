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
use App\Models\Identity\User;
use App\Services\Entities\OwnEntity;
use App\Support\Insights\ReportLibraryPresenter;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class MyInsightsController
{
    public function overview(#[CurrentUser] User $owner, OwnEntity $entities, GetInsightsOverview $overview): JsonResponse
    {
        return ApiResponse::resource(new InsightsOverviewResource($overview->handle($owner, $entities->of($owner))));
    }

    public function results(IndexInsightsPageRequest $request, #[CurrentUser] User $owner, OwnEntity $entities, ListInsightsResults $results): JsonResponse
    {
        return ApiResponse::paginated($results->handle($owner, $entities->of($owner), $request->page()), ResultRefResource::class);
    }

    public function library(IndexInsightsPageRequest $request, #[CurrentUser] User $owner, OwnEntity $entities, ListReportLibrary $library): JsonResponse
    {
        return ApiResponse::paginated($library->handle($owner, $entities->of($owner), $request->page()), ReportLibraryRowResource::class, [
            'coming_soon' => ReportLibraryPresenter::comingSoon(),
        ]);
    }
}
