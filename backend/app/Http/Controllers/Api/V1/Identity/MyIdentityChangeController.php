<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\CancelIdentityChange;
use App\Actions\Identity\ListOwnIdentityChanges;
use App\Actions\Identity\SubmitIdentityChange;
use App\Http\Requests\Identity\SubmitOwnIdentityChangeRequest;
use App\Http\Resources\Identity\OwnIdentityChangeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\IdentityChangeRequest;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class MyIdentityChangeController
{
    public function index(#[CurrentUser] User $user, ListOwnIdentityChanges $list): JsonResponse
    {
        return ApiResponse::paginated($list->handle($user), OwnIdentityChangeResource::class);
    }

    public function store(SubmitOwnIdentityChangeRequest $request, #[CurrentUser] User $user, SubmitIdentityChange $submit): JsonResponse
    {
        return ApiResponse::resource(new OwnIdentityChangeResource($submit->handle($user, $user, $request->draft())), Response::HTTP_CREATED);
    }

    public function cancel(IdentityChangeRequest $identityChange, CancelIdentityChange $cancel): JsonResponse
    {
        return ApiResponse::resource(new OwnIdentityChangeResource($cancel->handle($identityChange)));
    }
}
