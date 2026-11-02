<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\UpdateRolePermissions;
use App\Http\Requests\Identity\UpdateRolePermissionsRequest;
use App\Http\Resources\Identity\RoleResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\Role;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class RolePermissionsController
{
    public function __invoke(UpdateRolePermissionsRequest $request, Role $role, #[CurrentUser] User $actor, UpdateRolePermissions $updatePermissions): JsonResponse
    {
        $updated = $updatePermissions->handle($actor, $role, $request->permissionKeys());

        return ApiResponse::data([
            'role' => (new RoleResource($updated))->resolve($request),
            'permission_keys' => $updated->permissionKeys(),
        ]);
    }
}
