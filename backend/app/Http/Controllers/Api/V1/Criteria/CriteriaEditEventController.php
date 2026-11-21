<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Criteria;

use App\Actions\Criteria\ListCriteriaEditEvents;
use App\Http\Requests\Criteria\IndexCriteriaEditEventsRequest;
use App\Http\Resources\Criteria\CriteriaEditEventResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CriteriaEditEventController
{
    public function __invoke(IndexCriteriaEditEventsRequest $request, ListCriteriaEditEvents $listEvents): JsonResponse
    {
        return ApiResponse::paginated($listEvents->handle($request->perPage()), CriteriaEditEventResource::class);
    }
}
