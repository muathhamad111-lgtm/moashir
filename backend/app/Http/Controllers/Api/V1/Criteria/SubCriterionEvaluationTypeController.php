<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Criteria;

use App\Actions\Criteria\SwitchEvaluationType;
use App\Http\Requests\Criteria\SwitchEvaluationTypeRequest;
use App\Http\Resources\Criteria\SubCriterionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Criteria\SubCriterion;
use App\Models\Identity\User;
use App\Services\Criteria\CriteriaTreeLoader;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class SubCriterionEvaluationTypeController
{
    public function __invoke(SwitchEvaluationTypeRequest $request, SubCriterion $sub, #[CurrentUser] User $actor, SwitchEvaluationType $switchType, CriteriaTreeLoader $loader): JsonResponse
    {
        $switched = $switchType->handle($actor, $sub, $request->evaluationType(), $request->confirmsPointDeletion());

        return ApiResponse::resource(new SubCriterionResource($loader->loadSub($switched)));
    }
}
