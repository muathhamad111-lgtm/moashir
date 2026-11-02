<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Entities;

use App\Actions\Entities\ApproveEntityEmailChange;
use App\Actions\Entities\ListEntityEmailChanges;
use App\Actions\Entities\RejectEntityEmailChange;
use App\Http\Requests\Entities\IndexEmailChangesRequest;
use App\Http\Requests\Identity\RequiredReasonRequest;
use App\Http\Resources\Entities\EmailChangeRequestResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\EmailChangeRequest;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class EntityEmailChangeController
{
    public function index(IndexEmailChangesRequest $request, ListEntityEmailChanges $listChanges): JsonResponse
    {
        return ApiResponse::paginated($listChanges->handle($request->filters()), EmailChangeRequestResource::class);
    }

    public function approve(EmailChangeRequest $emailChange, #[CurrentUser] User $actor, ApproveEntityEmailChange $approveChange): JsonResponse
    {
        return ApiResponse::resource(new EmailChangeRequestResource($approveChange->handle($actor, $emailChange)->load('entity')));
    }

    public function reject(RequiredReasonRequest $request, EmailChangeRequest $emailChange, #[CurrentUser] User $actor, RejectEntityEmailChange $rejectChange): JsonResponse
    {
        return ApiResponse::resource(new EmailChangeRequestResource($rejectChange->handle($actor, $emailChange, $request->reason())->load('entity')));
    }
}
