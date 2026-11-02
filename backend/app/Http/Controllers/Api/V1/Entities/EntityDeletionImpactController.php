<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\GetEntityDeletionImpact;
use App\Http\Resources\Entities\EntityDeletionImpactResource;
use App\Http\Responses\ApiResponse;
use App\Models\Entities\Entity;
use Illuminate\Http\JsonResponse;

final class EntityDeletionImpactController
{
    public function __invoke(Entity $entity, GetEntityDeletionImpact $deletionImpact): JsonResponse
    {
        return ApiResponse::resource(new EntityDeletionImpactResource($deletionImpact->handle($entity)));
    }
}
