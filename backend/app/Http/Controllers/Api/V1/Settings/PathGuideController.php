<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Enums\Assessments\AssessmentPath;
use App\Http\Resources\Settings\PathGuideResource;
use App\Http\Responses\ApiResponse;
use App\Services\Settings\PathSettingsReader;
use Illuminate\Http\JsonResponse;

final class PathGuideController
{
    public function __invoke(AssessmentPath $path, PathSettingsReader $reader): JsonResponse
    {
        return ApiResponse::resource(new PathGuideResource($reader->read($path)));
    }
}
