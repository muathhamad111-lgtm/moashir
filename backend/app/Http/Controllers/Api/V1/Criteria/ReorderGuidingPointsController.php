<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Criteria;

use App\Actions\Criteria\ReorderGuidingPoints;
use App\Http\Requests\Criteria\ReorderCriteriaNodesRequest;
use App\Http\Resources\Criteria\GuidingPointResource;
use App\Http\Responses\ApiResponse;
use App\Models\Criteria\SubCriterion;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class ReorderGuidingPointsController
{
    public function __invoke(ReorderCriteriaNodesRequest $request, SubCriterion $sub, #[CurrentUser] User $actor, ReorderGuidingPoints $reorderPoints): JsonResponse
    {
        return ApiResponse::data(GuidingPointResource::collection($reorderPoints->handle($actor, $sub, $request->orderedIds()))->resolve($request));
    }
}
