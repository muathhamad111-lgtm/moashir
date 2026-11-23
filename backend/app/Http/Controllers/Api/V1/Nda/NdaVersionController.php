<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Nda;

use App\Actions\Nda\ListNdaVersions;
use App\Http\Requests\Nda\PageNdaRequest;
use App\Http\Resources\Nda\NdaVersionResource;
use App\Http\Resources\Nda\NdaVersionSummaryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\TermsVersion;
use Illuminate\Http\JsonResponse;

final class NdaVersionController
{
    public function index(PageNdaRequest $request, ListNdaVersions $list): JsonResponse
    {
        return ApiResponse::paginated($list->handle($request->perPage()), NdaVersionSummaryResource::class);
    }

    public function show(TermsVersion $ndaVersion): JsonResponse
    {
        return ApiResponse::resource(new NdaVersionResource($ndaVersion));
    }
}
