<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Criteria;

use App\Actions\Criteria\DistributeSubWeights;
use App\Http\Requests\Criteria\UpdateChildWeightsRequest;
use App\Http\Resources\Criteria\SubCriterionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Criteria\MainCriterion;
use App\Models\Identity\User;
use App\Services\Criteria\CriteriaTreeLoader;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class SubWeightsController
{
    public function __invoke(UpdateChildWeightsRequest $request, MainCriterion $main, #[CurrentUser] User $actor, DistributeSubWeights $distribute, CriteriaTreeLoader $loader): JsonResponse
    {
        $updated = $distribute->handle($actor, $main, $request->assignments());

        return ApiResponse::data(SubCriterionResource::collection($loader->loadMain($updated)->subCriteria)->resolve($request));
    }
}
