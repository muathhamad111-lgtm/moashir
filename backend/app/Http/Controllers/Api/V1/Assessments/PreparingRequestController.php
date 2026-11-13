<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assessments;

use App\Actions\Assessments\ListPreparingRequests;
use App\Data\Assessments\PathCount;
use App\Http\Requests\Assessments\IndexPreparingRequestsRequest;
use App\Http\Resources\Assessments\PreparingRequestResource;
use App\Http\Responses\ApiResponse;
use App\Services\Assessments\RequestPathCounts;
use Illuminate\Http\JsonResponse;

final class PreparingRequestController
{
    public function __invoke(IndexPreparingRequestsRequest $request, ListPreparingRequests $list, RequestPathCounts $counts): JsonResponse
    {
        $filters = $request->filters();

        return ApiResponse::paginated($list->handle($filters), PreparingRequestResource::class, [
            'path' => $filters->path->value,
            'path_counts' => array_map(static fn (PathCount $count): array => $count->toArray(), $counts->forQueue($request->followedPaths())),
        ]);
    }
}
