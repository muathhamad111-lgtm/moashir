<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\GetPermissionMatrix;
use App\Actions\Identity\UpdatePermissionLabels;
use App\Http\Requests\Identity\IndexPermissionsRequest;
use App\Http\Requests\Identity\UpdatePermissionLabelsRequest;
use App\Http\Resources\Identity\PermissionResource;
use App\Http\Resources\Identity\RoleResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\Permission;
use Illuminate\Http\JsonResponse;

final class PermissionController
{
    public function index(IndexPermissionsRequest $request, GetPermissionMatrix $getMatrix): JsonResponse
    {
        $matrix = $getMatrix->handle($request->module());

        return ApiResponse::data([
            'permissions' => PermissionResource::collection($matrix->permissions)->resolve($request),
            'roles' => RoleResource::collection($matrix->roles)->resolve($request),
            'matrix' => $matrix->grants(),
        ]);
    }

    public function update(UpdatePermissionLabelsRequest $request, Permission $permission, UpdatePermissionLabels $updateLabels): JsonResponse
    {
        return ApiResponse::resource(new PermissionResource($updateLabels->handle($permission, $request->label(), $request->description())));
    }
}
