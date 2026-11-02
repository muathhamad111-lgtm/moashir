<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Criteria;

use App\Actions\Criteria\ToggleSubCriterion;
use App\Http\Requests\Criteria\ToggleCriteriaNodeRequest;
use App\Http\Resources\Criteria\SubCriterionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Criteria\SubCriterion;
use App\Models\Identity\User;
use App\Services\Criteria\CriteriaTreeLoader;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class SubCriterionActivationController
{
    public function __invoke(ToggleCriteriaNodeRequest $request, SubCriterion $sub, #[CurrentUser] User $actor, ToggleSubCriterion $toggleSub, CriteriaTreeLoader $loader): JsonResponse
    {
        return ApiResponse::resource(new SubCriterionResource($loader->loadSub($toggleSub->handle($actor, $sub, $request->isActive()))));
    }
}
