<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Award;

use App\Actions\Award\ListFilteringQueue;
use App\Actions\Award\ShowFilteringRequest;
use App\Actions\Award\SummarizeFilteringQueue;
use App\Http\Requests\Award\IndexFilteringQueueRequest;
use App\Http\Resources\Award\FilteringDetailResource;
use App\Http\Resources\Award\FilteringRowResource;
use App\Http\Responses\ApiResponse;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class FilteringController
{
    public function index(IndexFilteringQueueRequest $request, AwardCycle $cycle, ListFilteringQueue $list, SummarizeFilteringQueue $summarize): JsonResponse
    {
        $filters = $request->filters();

        return ApiResponse::paginated($list->handle($cycle, $filters), FilteringRowResource::class, $summarize->handle($cycle, $filters));
    }

    public function show(AwardCycle $cycle, Assessment $assessment, #[CurrentUser] User $viewer, ShowFilteringRequest $show): JsonResponse
    {
        return ApiResponse::resource(new FilteringDetailResource($show->handle($viewer, $assessment)));
    }
}
