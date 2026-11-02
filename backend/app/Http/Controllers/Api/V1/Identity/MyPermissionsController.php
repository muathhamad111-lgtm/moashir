<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\GetOwnPermissions;
use App\Http\Resources\Identity\PermissionLabelResource;
use App\Http\Resources\Identity\RoleLabelResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MyPermissionsController
{
    public function __invoke(Request $request, #[CurrentUser] User $user, GetOwnPermissions $getPermissions): JsonResponse
    {
        $granted = $getPermissions->handle($user);

        return ApiResponse::data([
            'roles' => RoleLabelResource::collection($granted['roles'])->resolve($request),
            'permissions' => PermissionLabelResource::collection($granted['permissions'])->resolve($request),
        ]);
    }
}
