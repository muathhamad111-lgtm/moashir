<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Certificates;

use App\Actions\Certificates\ListCertificates;
use App\Actions\Certificates\ReissueCertificate;
use App\Actions\Certificates\RevokeCertificate;
use App\Http\Requests\Certificates\CertificateReasonRequest;
use App\Http\Resources\Certificates\CertificateResource;
use App\Http\Responses\ApiResponse;
use App\Models\Certificates\Certificate;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class CertificateLifecycleController
{
    public function revoke(CertificateReasonRequest $request, Certificate $certificate, #[CurrentUser] User $actor, RevokeCertificate $revoke): JsonResponse
    {
        return ApiResponse::resource(new CertificateResource($revoke->handle($actor, $certificate, $request->reason())->load(ListCertificates::RELATIONS)));
    }

    public function reissue(CertificateReasonRequest $request, Certificate $certificate, #[CurrentUser] User $actor, ReissueCertificate $reissue): JsonResponse
    {
        $replacement = $reissue->handle($actor, $certificate, $request->reason());

        return ApiResponse::resource(new CertificateResource($replacement->load(ListCertificates::RELATIONS)), Response::HTTP_CREATED);
    }
}
