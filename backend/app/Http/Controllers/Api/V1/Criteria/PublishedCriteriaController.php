<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Criteria;

use App\Actions\Criteria\GetPublishedCriteriaTree;
use App\Http\Requests\Criteria\ShowPublishedCriteriaRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PublishedCriteriaController
{
    public function __invoke(ShowPublishedCriteriaRequest $request, GetPublishedCriteriaTree $getTree): JsonResponse
    {
        return ApiResponse::data($getTree->handle($request->requestedVersion()));
    }
}
