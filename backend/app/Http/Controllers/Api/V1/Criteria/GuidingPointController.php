<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Criteria;

use App\Actions\Criteria\CreateGuidingPoint;
use App\Actions\Criteria\RemoveGuidingPoint;
use App\Actions\Criteria\UpdateGuidingPoint;
use App\Http\Requests\Criteria\StoreGuidingPointRequest;
use App\Http\Requests\Criteria\UpdateGuidingPointRequest;
use App\Http\Resources\Criteria\GuidingPointResource;
use App\Http\Resources\Criteria\SubCriterionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\SubCriterion;
use App\Models\Identity\User;
use App\Services\Criteria\CriteriaTreeLoader;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class GuidingPointController
{
    public function store(StoreGuidingPointRequest $request, SubCriterion $sub, #[CurrentUser] User $actor, CreateGuidingPoint $createPoint, CriteriaTreeLoader $loader): JsonResponse
    {
        $point = $createPoint->handle($actor, $sub, $request->attributesData(), $request->isActive());

        return ApiResponse::data($this->pointWithSub($point, $loader), Response::HTTP_CREATED);
    }

    public function update(UpdateGuidingPointRequest $request, GuidingPoint $point, #[CurrentUser] User $actor, UpdateGuidingPoint $updatePoint, CriteriaTreeLoader $loader): JsonResponse
    {
        return ApiResponse::data($this->pointWithSub($updatePoint->handle($actor, $point, $request->attributesData($point)), $loader));
    }

    public function destroy(GuidingPoint $point, #[CurrentUser] User $actor, RemoveGuidingPoint $removePoint, CriteriaTreeLoader $loader): JsonResponse
    {
        $sub = $removePoint->handle($actor, $point);

        return ApiResponse::data(['sub' => new SubCriterionResource($loader->loadSub($sub))->resolve(request())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function pointWithSub(GuidingPoint $point, CriteriaTreeLoader $loader): array
    {
        return [
            'point' => new GuidingPointResource($loader->loadPoint($point))->resolve(request()),
            'sub' => new SubCriterionResource($loader->loadSub($point->subCriterion))->resolve(request()),
        ];
    }
}
