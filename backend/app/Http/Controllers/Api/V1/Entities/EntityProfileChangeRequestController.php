<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\DecideEntityProfileChange;
use App\Actions\Entities\ListEntityProfileChangeRequests;
use App\Actions\Entities\ShowEntityProfileChangeRequest;
use App\Http\Requests\Entities\DecideProfileChangeRequest;
use App\Http\Requests\Entities\IndexProfileChangeRequestsRequest;
use App\Http\Resources\Entities\ProfileChangeRequestDetailResource;
use App\Http\Resources\Entities\ProfileChangeRequestResource;
use App\Http\Responses\ApiResponse;
use App\Models\Entities\EntityProfileChangeRequest;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class EntityProfileChangeRequestController
{
    public function index(IndexProfileChangeRequestsRequest $request, ListEntityProfileChangeRequests $list): JsonResponse
    {
        return ApiResponse::paginated($list->handle($request->filters()), ProfileChangeRequestResource::class);
    }

    public function show(EntityProfileChangeRequest $profileChange, ShowEntityProfileChangeRequest $show): JsonResponse
    {
        return ApiResponse::resource(new ProfileChangeRequestDetailResource($show->handle($profileChange)));
    }

    public function decide(
        DecideProfileChangeRequest $request,
        EntityProfileChangeRequest $profileChange,
        #[CurrentUser] User $reviewer,
        DecideEntityProfileChange $decide,
        ShowEntityProfileChangeRequest $show,
    ): JsonResponse {
        return ApiResponse::resource(new ProfileChangeRequestDetailResource($show->handle($decide->handle($reviewer, $profileChange, $request->decision()))));
    }
}
