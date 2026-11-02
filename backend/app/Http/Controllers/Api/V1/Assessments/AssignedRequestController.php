<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assessments;

use App\Actions\Assessments\ListAssignedRequests;
use App\Http\Requests\Assessments\IndexAssignedRequestsRequest;
use App\Http\Resources\Assessments\AssignedRequestResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class AssignedRequestController
{
    public function __invoke(IndexAssignedRequestsRequest $request, #[CurrentUser] User $assessor, ListAssignedRequests $list): JsonResponse
    {
        return ApiResponse::paginated($list->handle($assessor, $request->tab(), $request->perPage()), AssignedRequestResource::class);
    }
}
