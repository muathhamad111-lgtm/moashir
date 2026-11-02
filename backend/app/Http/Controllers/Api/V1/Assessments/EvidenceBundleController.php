<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assessments;

use App\Actions\Assessments\IssueEvidenceBundleDownload;
use App\Actions\Assessments\RequestEvidenceBundle;
use App\Enums\Assessments\EvidenceBundleStatus;
use App\Http\Resources\Assessments\EvidenceBundleResource;
use App\Http\Resources\Files\SignedDownloadResource;
use App\Http\Responses\ApiResponse;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\EvidenceBundle;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class EvidenceBundleController
{
    public function store(Assessment $assessment, #[CurrentUser] User $actor, RequestEvidenceBundle $requestBundle): JsonResponse
    {
        $bundle = $requestBundle->handle($actor, $assessment);

        return ApiResponse::resource(
            new EvidenceBundleResource($bundle),
            $bundle->status === EvidenceBundleStatus::Preparing ? Response::HTTP_ACCEPTED : Response::HTTP_CREATED,
        );
    }

    public function show(Assessment $assessment, EvidenceBundle $bundle): JsonResponse
    {
        return ApiResponse::resource(new EvidenceBundleResource($bundle));
    }

    public function download(Assessment $assessment, EvidenceBundle $bundle, #[CurrentUser] User $actor, IssueEvidenceBundleDownload $issue): JsonResponse
    {
        return ApiResponse::resource(new SignedDownloadResource($issue->handle($actor, $bundle)));
    }
}
