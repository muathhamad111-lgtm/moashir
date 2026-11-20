<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\ApproveIdentityChange;
use App\Actions\Identity\ListIdentityChanges;
use App\Actions\Identity\RejectIdentityChange;
use App\Actions\Identity\ShowIdentityChange;
use App\Http\Requests\Identity\DecideIdentityChangeRequest;
use App\Http\Requests\Identity\IndexIdentityChangesRequest;
use App\Http\Resources\Identity\IdentityChangeRequestDetailResource;
use App\Http\Resources\Identity\IdentityChangeRequestResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\IdentityChangeRequest;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class IdentityChangeController
{
    public function index(IndexIdentityChangesRequest $request, ListIdentityChanges $list): JsonResponse
    {
        return ApiResponse::paginated($list->handle($request->filters()), IdentityChangeRequestResource::class);
    }

    public function show(IdentityChangeRequest $identityChange, ShowIdentityChange $show): JsonResponse
    {
        return ApiResponse::resource(new IdentityChangeRequestDetailResource($show->handle($identityChange)));
    }

    public function approve(DecideIdentityChangeRequest $request, IdentityChangeRequest $identityChange, #[CurrentUser] User $reviewer, ApproveIdentityChange $approve): JsonResponse
    {
        return ApiResponse::resource(new IdentityChangeRequestResource($approve->handle($reviewer, $identityChange, $request->note())->loadMissing(['user', 'submitter', 'reviewer'])));
    }

    public function reject(DecideIdentityChangeRequest $request, IdentityChangeRequest $identityChange, #[CurrentUser] User $reviewer, RejectIdentityChange $reject): JsonResponse
    {
        return ApiResponse::resource(new IdentityChangeRequestResource($reject->handle($reviewer, $identityChange, $request->requiredNote())->loadMissing(['user', 'submitter', 'reviewer'])));
    }
}
