<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assessments;

use App\Actions\Assessments\ListRequestQueue;
use App\Actions\Assessments\ShowRequest;
use App\Actions\Assessments\SummarizeRequestQueue;
use App\Http\Requests\Assessments\IndexRequestQueueRequest;
use App\Http\Resources\Assessments\RequestDetailResource;
use App\Http\Resources\Assessments\RequestQueueItemResource;
use App\Http\Responses\ApiResponse;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class RequestQueueController
{
    public function index(IndexRequestQueueRequest $request, ListRequestQueue $list, SummarizeRequestQueue $summarize): JsonResponse
    {
        $filters = $request->filters();

        return ApiResponse::paginated($list->handle($filters), RequestQueueItemResource::class, ['stats' => $summarize->handle($filters)]);
    }

    public function show(Assessment $assessment, #[CurrentUser] User $viewer, ShowRequest $show): JsonResponse
    {
        return ApiResponse::resource(new RequestDetailResource($show->handle($viewer, $assessment)));
    }
}
