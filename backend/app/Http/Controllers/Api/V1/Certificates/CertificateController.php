<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Certificates;

use App\Actions\Certificates\IssueCertificateDownload;
use App\Actions\Certificates\ListCertificates;
use App\Http\Requests\Certificates\IndexCertificatesRequest;
use App\Http\Resources\Certificates\CertificateResource;
use App\Http\Resources\Files\SignedDownloadResource;
use App\Http\Responses\ApiResponse;
use App\Models\Certificates\Certificate;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class CertificateController
{
    public function index(IndexCertificatesRequest $request, #[CurrentUser] User $viewer, ListCertificates $listCertificates): JsonResponse
    {
        $filters = $request->filters();

        return ApiResponse::paginated($listCertificates->handle($viewer, $filters), CertificateResource::class, [
            'status_counts' => $listCertificates->statusCountsOf($viewer, $filters),
        ]);
    }

    public function show(Certificate $certificate): JsonResponse
    {
        return ApiResponse::resource(new CertificateResource($certificate->load(ListCertificates::RELATIONS)));
    }

    public function download(Certificate $certificate, #[CurrentUser] User $actor, IssueCertificateDownload $issueDownload): JsonResponse
    {
        return ApiResponse::resource(new SignedDownloadResource($issueDownload->handle($actor, $certificate)));
    }
}
