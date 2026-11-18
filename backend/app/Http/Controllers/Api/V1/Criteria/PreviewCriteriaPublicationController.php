<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Criteria;

use App\Actions\Criteria\PreviewCriteriaPublication;
use App\Http\Resources\Criteria\CriteriaPublicationPreviewResource;
use App\Http\Responses\ApiResponse;
use App\Models\Criteria\CriteriaVersion;
use Illuminate\Http\JsonResponse;

final class PreviewCriteriaPublicationController
{
    public function __invoke(CriteriaVersion $version, PreviewCriteriaPublication $preview): JsonResponse
    {
        return ApiResponse::resource(new CriteriaPublicationPreviewResource($preview->handle($version)));
    }
}
