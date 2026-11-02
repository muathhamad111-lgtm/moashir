<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Http\Resources\Identity\RoleResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RoleController
{
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::data(RoleResource::collection(Role::query()->orderBy('dashboard_priority')->get())->resolve($request));
    }
}
