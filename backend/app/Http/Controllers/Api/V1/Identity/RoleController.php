<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Actions\Identity\ListRoles;
use App\Http\Resources\Identity\RoleResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RoleController
{
    public function index(Request $request, ListRoles $roles): JsonResponse
    {
        return ApiResponse::data(RoleResource::collection($roles->handle())->resolve($request));
    }
}
