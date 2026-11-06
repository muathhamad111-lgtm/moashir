<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Certificates;

use App\Actions\Certificates\IssueCertificateForCompletedReview;
use App\Actions\Certificates\ListCertificates;
use App\Http\Requests\Assessments\AssessmentActionRequest;
use App\Http\Resources\Certificates\CertificateResource;
use App\Http\Responses\ApiResponse;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class AccreditedCertificateController
{
    public function store(AssessmentActionRequest $request, Assessment $assessment, #[CurrentUser] User $actor, IssueCertificateForCompletedReview $issue): JsonResponse
    {
        $certificate = $issue->handle($actor, $assessment, $request->reason());

        return ApiResponse::resource(
            new CertificateResource($certificate->load(ListCertificates::RELATIONS)),
            $certificate->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK,
        );
    }
}
