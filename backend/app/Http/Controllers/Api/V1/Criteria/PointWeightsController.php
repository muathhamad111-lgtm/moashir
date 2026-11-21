<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Criteria;

use App\Actions\Criteria\DistributePointWeights;
use App\Http\Requests\Criteria\UpdateChildWeightsRequest;
use App\Http\Resources\Criteria\GuidingPointResource;
use App\Http\Responses\ApiResponse;
use App\Models\Criteria\SubCriterion;
use App\Models\Identity\User;
use App\Services\Criteria\CriteriaTreeLoader;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class PointWeightsController
{
    public function __invoke(UpdateChildWeightsRequest $request, SubCriterion $sub, #[CurrentUser] User $actor, DistributePointWeights $distribute, CriteriaTreeLoader $loader): JsonResponse
    {
        $updated = $distribute->handle($actor, $sub, $request->assignments());

        return ApiResponse::data(GuidingPointResource::collection($loader->loadSub($updated)->guidingPoints)->resolve($request));
    }
}
