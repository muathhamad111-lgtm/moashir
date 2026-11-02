<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Award;

use App\Actions\Award\ListCycleCandidates;
use App\Http\Requests\Award\ListCycleCandidatesRequest;
use App\Http\Resources\Award\CycleCandidateResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CycleCandidatesController
{
    public function __invoke(ListCycleCandidatesRequest $request, ListCycleCandidates $list): JsonResponse
    {
        return ApiResponse::data(CycleCandidateResource::collection($list->handle($request->role(), $request->search()))->resolve($request));
    }
}
