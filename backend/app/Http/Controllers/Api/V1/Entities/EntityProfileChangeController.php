<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\ListStaffEntityProfileChanges;
use App\Http\Requests\Entities\IndexProfileChangesRequest;
use App\Http\Resources\Entities\StaffProfileChangeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Entities\Entity;
use Illuminate\Http\JsonResponse;

final class EntityProfileChangeController
{
    public function __invoke(IndexProfileChangesRequest $request, Entity $entity, ListStaffEntityProfileChanges $listChanges): JsonResponse
    {
        return ApiResponse::paginated($listChanges->handle($entity, $request->perPage()), StaffProfileChangeResource::class);
    }
}
