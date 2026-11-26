<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Award;

use App\Actions\Award\ListOpenAwardCycles;
use App\Http\Resources\Award\PublicCycleResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicOpenCyclesController
{
    public function __invoke(Request $request, ListOpenAwardCycles $open): JsonResponse
    {
        return ApiResponse::data(PublicCycleResource::collection($open->handle())->resolve($request));
    }
}
