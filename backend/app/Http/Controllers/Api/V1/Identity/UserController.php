<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\InviteStaffUser;
use App\Actions\Identity\ListStaffUsers;
use App\Actions\Identity\SummarizeStaffUsers;
use App\Actions\Identity\UpdateStaffUser;
use App\Http\Requests\Identity\IndexUsersRequest;
use App\Http\Requests\Identity\StoreUserRequest;
use App\Http\Requests\Identity\UpdateUserRequest;
use App\Http\Resources\Identity\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class UserController
{
    public function index(IndexUsersRequest $request, ListStaffUsers $listUsers, SummarizeStaffUsers $summarize): JsonResponse
    {
        $filters = $request->filters();

        return ApiResponse::paginated($listUsers->handle($filters), UserResource::class, ['stats' => $summarize->handle($filters)]);
    }

    public function store(StoreUserRequest $request, #[CurrentUser] User $actor, InviteStaffUser $inviteUser): JsonResponse
    {
        return ApiResponse::resource(new UserResource($inviteUser->handle($actor, $request->staffUser())->load(User::ADMINISTRATION_RELATIONS)), Response::HTTP_CREATED);
    }

    public function show(User $managedUser): JsonResponse
    {
        return ApiResponse::resource(new UserResource($managedUser->load(User::ADMINISTRATION_RELATIONS)));
    }

    public function update(UpdateUserRequest $request, User $managedUser, #[CurrentUser] User $actor, UpdateStaffUser $updateUser): JsonResponse
    {
        return ApiResponse::resource(new UserResource($updateUser->handle($actor, $managedUser, $request->changes())->load(User::ADMINISTRATION_RELATIONS)));
    }
}
