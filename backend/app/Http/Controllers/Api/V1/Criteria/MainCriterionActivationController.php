<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Criteria;

use App\Actions\Criteria\ToggleMainCriterion;
use App\Http\Requests\Criteria\ToggleCriteriaNodeRequest;
use App\Http\Resources\Criteria\MainCriterionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Criteria\MainCriterion;
use App\Models\Identity\User;
use App\Services\Criteria\CriteriaTreeLoader;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class MainCriterionActivationController
{
    public function __invoke(ToggleCriteriaNodeRequest $request, MainCriterion $main, #[CurrentUser] User $actor, ToggleMainCriterion $toggleMain, CriteriaTreeLoader $loader): JsonResponse
    {
        return ApiResponse::resource(new MainCriterionResource($loader->loadMain($toggleMain->handle($actor, $main, $request->isActive()))));
    }
}
