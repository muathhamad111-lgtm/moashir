<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\ListEntityTimeline;
use App\Http\Requests\Entities\IndexEntityTimelineRequest;
use App\Http\Resources\Entities\EntityTimelineEntryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Entities\Entity;
use Illuminate\Http\JsonResponse;

final class EntityTimelineController
{
    public function __invoke(IndexEntityTimelineRequest $request, Entity $entity, ListEntityTimeline $listTimeline): JsonResponse
    {
        return ApiResponse::paginated(
            $listTimeline->handle($entity, $request->perPage(), $request->scopeFor($entity)),
            EntityTimelineEntryResource::class,
        );
    }
}
