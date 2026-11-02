<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Criteria;

use App\Actions\Criteria\ToggleGuidingPoint;
use App\Http\Requests\Criteria\ToggleCriteriaNodeRequest;
use App\Http\Resources\Criteria\GuidingPointResource;
use App\Http\Responses\ApiResponse;
use App\Models\Criteria\GuidingPoint;
use App\Models\Identity\User;
use App\Services\Criteria\CriteriaTreeLoader;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class GuidingPointActivationController
{
    public function __invoke(ToggleCriteriaNodeRequest $request, GuidingPoint $point, #[CurrentUser] User $actor, ToggleGuidingPoint $togglePoint, CriteriaTreeLoader $loader): JsonResponse
    {
        return ApiResponse::resource(new GuidingPointResource($loader->loadPoint($togglePoint->handle($actor, $point, $request->isActive()))));
    }
}
