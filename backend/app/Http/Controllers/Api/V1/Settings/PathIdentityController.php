<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Resources\Settings\PathIdentityResource;
use App\Http\Responses\ApiResponse;
use App\Services\Paths\PathIdentityReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PathIdentityController
{
    public function __invoke(Request $request, PathIdentityReader $reader): JsonResponse
    {
        return ApiResponse::data(PathIdentityResource::collection($reader->all())->resolve($request));
    }
}
