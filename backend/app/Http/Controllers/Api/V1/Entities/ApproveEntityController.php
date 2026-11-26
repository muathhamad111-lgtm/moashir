<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\ApproveEntity;
use App\Http\Requests\Identity\OptionalReasonRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Entities\EntityRecordScope;
use App\Support\Entities\EntityResourceVariant;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class ApproveEntityController
{
    public function __invoke(OptionalReasonRequest $request, Entity $entity, #[CurrentUser] User $actor, ApproveEntity $approve, EntityRecordScope $scope): JsonResponse
    {
        $updated = $approve->handle($actor, $entity, $request->reason());

        return ApiResponse::resource(EntityResourceVariant::of($updated, $scope->accessOf($actor)));
    }
}
