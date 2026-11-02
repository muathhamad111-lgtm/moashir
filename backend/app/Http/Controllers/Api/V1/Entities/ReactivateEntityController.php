<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\ReactivateEntity;
use App\Http\Requests\Identity\RequiredReasonRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Entities\EntityRecordScope;
use App\Support\Entities\EntityResourceVariant;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class ReactivateEntityController
{
    public function __invoke(RequiredReasonRequest $request, Entity $entity, #[CurrentUser] User $actor, ReactivateEntity $reactivate, EntityRecordScope $scope): JsonResponse
    {
        $updated = $reactivate->handle($actor, $entity, $request->reason());

        return ApiResponse::resource(EntityResourceVariant::of($updated, $scope->accessOf($actor)));
    }
}
