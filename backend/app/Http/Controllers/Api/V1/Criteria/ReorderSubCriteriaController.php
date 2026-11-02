<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Criteria;

use App\Actions\Criteria\ReorderSubCriteria;
use App\Http\Requests\Criteria\ReorderCriteriaNodesRequest;
use App\Http\Resources\Criteria\SubCriterionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Criteria\MainCriterion;
use App\Models\Identity\User;
use App\Services\Criteria\CriteriaTreeLoader;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class ReorderSubCriteriaController
{
    public function __invoke(ReorderCriteriaNodesRequest $request, MainCriterion $main, #[CurrentUser] User $actor, ReorderSubCriteria $reorderSubs, CriteriaTreeLoader $loader): JsonResponse
    {
        $reorderSubs->handle($actor, $main, $request->orderedIds());

        return ApiResponse::data(SubCriterionResource::collection($loader->loadMain($main)->subCriteria)->resolve($request));
    }
}
