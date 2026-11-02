<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Criteria;

use App\Actions\Criteria\CreateSubCriterion;
use App\Actions\Criteria\RemoveSubCriterion;
use App\Actions\Criteria\UpdateSubCriterion;
use App\Http\Requests\Criteria\StoreSubCriterionRequest;
use App\Http\Requests\Criteria\UpdateSubCriterionRequest;
use App\Http\Resources\Criteria\GuidingPointResource;
use App\Http\Resources\Criteria\SubCriterionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;
use App\Models\Identity\User;
use App\Services\Criteria\CriteriaTreeLoader;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class SubCriterionController
{
    public function store(StoreSubCriterionRequest $request, #[CurrentUser] User $actor, CreateSubCriterion $createSub, CriteriaTreeLoader $loader): JsonResponse
    {
        $main = MainCriterion::query()->whereHas('criteriaVersion')->findOrFail($request->mainCriterionId());
        $sub = $createSub->handle($actor, $main, $request->attributesData(), $request->isActive());

        return ApiResponse::resource(new SubCriterionResource($loader->loadSub($sub)), Response::HTTP_CREATED);
    }

    public function show(SubCriterion $sub, CriteriaTreeLoader $loader): JsonResponse
    {
        $loaded = $loader->loadSub($sub);

        return ApiResponse::data([
            'sub' => new SubCriterionResource($loaded)->resolve(request()),
            'points' => GuidingPointResource::collection($loaded->guidingPoints)->resolve(request()),
        ]);
    }

    public function update(UpdateSubCriterionRequest $request, SubCriterion $sub, #[CurrentUser] User $actor, UpdateSubCriterion $updateSub, CriteriaTreeLoader $loader): JsonResponse
    {
        $updated = $updateSub->handle($actor, $sub, $request->attributesData($sub));

        return ApiResponse::resource(new SubCriterionResource($loader->loadSub($updated)));
    }

    public function destroy(SubCriterion $sub, #[CurrentUser] User $actor, RemoveSubCriterion $removeSub): Response
    {
        $removeSub->handle($actor, $sub);

        return ApiResponse::noContent();
    }
}
