<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\CancelOwnProfileChange;
use App\Actions\Entities\ListOwnProfileChangeRequests;
use App\Actions\Entities\ShowEntityProfileChangeRequest;
use App\Http\Requests\Entities\IndexOwnProfileChangeRequestsRequest;
use App\Http\Resources\Entities\OwnEntityResource;
use App\Http\Resources\Entities\ProfileChangeRequestDetailResource;
use App\Http\Resources\Entities\ProfileChangeRequestResource;
use App\Http\Responses\ApiResponse;
use App\Models\Entities\EntityProfileChangeRequest;
use App\Models\Identity\User;
use App\Services\Entities\OwnEntity;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class MyProfileChangeRequestController
{
    public function index(IndexOwnProfileChangeRequestsRequest $request, #[CurrentUser] User $owner, ListOwnProfileChangeRequests $list): JsonResponse
    {
        return ApiResponse::paginated($list->handle($owner, $request->perPage()), ProfileChangeRequestResource::class);
    }

    public function pending(#[CurrentUser] User $owner, OwnEntity $ownEntity, ShowEntityProfileChangeRequest $show): JsonResponse
    {
        $pending = $ownEntity->of($owner)->load('pendingProfileChange')->pendingProfileChange;

        return $pending instanceof EntityProfileChangeRequest
            ? ApiResponse::resource(new ProfileChangeRequestDetailResource($show->handle($pending)))
            : ApiResponse::data(null);
    }

    public function cancel(#[CurrentUser] User $owner, CancelOwnProfileChange $cancel): JsonResponse
    {
        return ApiResponse::resource(new OwnEntityResource($cancel->handle($owner)->load(OwnEntityResource::RELATIONS)));
    }
}
