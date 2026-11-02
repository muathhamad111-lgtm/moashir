<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\LookupPrivacyRequestEntities;
use App\Http\Requests\Entities\LookupPrivacyEntitiesRequest;
use App\Http\Resources\Entities\EntityLookupResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PrivacyEntityLookupController
{
    public function __invoke(LookupPrivacyEntitiesRequest $request, LookupPrivacyRequestEntities $lookup): JsonResponse
    {
        $query = $request->lookupQuery();

        return ApiResponse::data(EntityLookupResource::collection($lookup->handle($query))->resolve($request), meta: ['limit' => $query->limit]);
    }
}
