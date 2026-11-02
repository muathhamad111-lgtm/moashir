<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\RestoreEntity;
use App\Http\Requests\Identity\RequiredReasonRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Entities\EntityRecordScope;
use App\Support\Entities\EntityResourceVariant;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class RestoreEntityController
{
    public function __invoke(RequiredReasonRequest $request, Entity $entity, #[CurrentUser] User $actor, RestoreEntity $restore, EntityRecordScope $scope): JsonResponse
    {
        $restored = $restore->handle($actor, $entity, $request->reason());

        return ApiResponse::resource(EntityResourceVariant::of($restored, $scope->accessOf($actor)));
    }
}
