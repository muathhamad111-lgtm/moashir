<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Certificates;

use App\Actions\Certificates\VerifyCertificate;
use App\Http\Resources\Certificates\CertificateVerificationResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CertificateVerificationController
{
    public function __invoke(Request $request, string $code, VerifyCertificate $verify): JsonResponse
    {
        return ApiResponse::resource(new CertificateVerificationResource($verify->handle($code, $request->ip())));
    }
}
