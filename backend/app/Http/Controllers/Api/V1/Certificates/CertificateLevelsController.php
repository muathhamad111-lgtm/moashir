<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Certificates;

use App\Http\Resources\Certificates\CertificateLevelsResource;
use App\Http\Responses\ApiResponse;
use App\Services\Certificates\CertificateSettingsReader;
use Illuminate\Http\JsonResponse;

final class CertificateLevelsController
{
    public function __invoke(CertificateSettingsReader $settings): JsonResponse
    {
        return ApiResponse::resource(new CertificateLevelsResource($settings->thresholds()));
    }
}
