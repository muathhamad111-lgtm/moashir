<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Criteria;

use App\Actions\Criteria\ApplyPointScoringType;
use App\Http\Requests\Criteria\ApplyPointScoringTypeRequest;
use App\Http\Resources\Criteria\GuidingPointResource;
use App\Http\Resources\Criteria\SubCriterionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Criteria\SubCriterion;
use App\Models\Identity\User;
use App\Services\Criteria\CriteriaTreeLoader;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class PointScoringTypeController
{
    public function __invoke(ApplyPointScoringTypeRequest $request, SubCriterion $sub, #[CurrentUser] User $actor, ApplyPointScoringType $apply, CriteriaTreeLoader $loader): JsonResponse
    {
        $updated = $loader->loadSub($apply->handle($actor, $sub, $request->chosenType()));

        return ApiResponse::data([
            'sub' => new SubCriterionResource($updated)->resolve($request),
            'points' => GuidingPointResource::collection($updated->guidingPoints)->resolve($request),
        ]);
    }
}
