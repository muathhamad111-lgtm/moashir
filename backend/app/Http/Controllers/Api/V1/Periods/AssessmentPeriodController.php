<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Periods;

use App\Actions\Periods\ListAssessmentPeriods;
use App\Http\Requests\Periods\IndexAssessmentPeriodsRequest;
use App\Http\Resources\Periods\AssessmentPeriodResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class AssessmentPeriodController
{
    public function index(IndexAssessmentPeriodsRequest $request, ListAssessmentPeriods $list): JsonResponse
    {
        return ApiResponse::paginated($list->handle($request->filters()), AssessmentPeriodResource::class);
    }
}
