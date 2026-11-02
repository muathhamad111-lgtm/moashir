<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\DeactivateEntity;
use App\Http\Requests\Identity\RequiredReasonRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Entities\EntityRecordScope;
use App\Support\Entities\EntityResourceVariant;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class DeactivateEntityController
{
    public function __invoke(RequiredReasonRequest $request, Entity $entity, #[CurrentUser] User $actor, DeactivateEntity $deactivate, EntityRecordScope $scope): JsonResponse
    {
        $updated = $deactivate->handle($actor, $entity, $request->reason());

        return ApiResponse::resource(EntityResourceVariant::of($updated, $scope->accessOf($actor)));
    }
}
