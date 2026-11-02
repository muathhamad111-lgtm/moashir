<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Criteria;

use App\Actions\Criteria\ReorderMainCriteria;
use App\Http\Requests\Criteria\ReorderCriteriaNodesRequest;
use App\Http\Resources\Criteria\MainCriterionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class ReorderMainCriteriaController
{
    public function __invoke(ReorderCriteriaNodesRequest $request, #[CurrentUser] User $actor, ReorderMainCriteria $reorderMains): JsonResponse
    {
        return ApiResponse::data(MainCriterionResource::collection($reorderMains->handle($actor, $request->orderedIds()))->resolve($request));
    }
}
