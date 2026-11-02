<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assessments;

use App\Actions\Assessments\IssueEvidenceDownload;
use App\Actions\Assessments\ListEvidence;
use App\Actions\Assessments\RemoveEvidence;
use App\Actions\Assessments\UploadEvidence;
use App\Http\Requests\Assessments\AssessmentActionRequest;
use App\Http\Requests\Assessments\ListEvidenceRequest;
use App\Http\Requests\Assessments\UploadEvidenceRequest;
use App\Http\Resources\Assessments\EvidenceFileResource;
use App\Http\Resources\Assessments\EvidenceListResource;
use App\Http\Resources\Files\SignedDownloadResource;
use App\Http\Responses\ApiResponse;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\EvidenceFile;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class EvidenceController
{
    public function index(ListEvidenceRequest $request, Assessment $assessment, #[CurrentUser] User $viewer, ListEvidence $list): JsonResponse
    {
        return ApiResponse::resource(new EvidenceListResource($list->handle($viewer, $assessment, $request->subId())));
    }

    public function store(UploadEvidenceRequest $request, Assessment $assessment, #[CurrentUser] User $actor, UploadEvidence $upload): JsonResponse
    {
        return ApiResponse::resource(new EvidenceFileResource($upload->handle($actor, $assessment, $request->upload())), Response::HTTP_CREATED);
    }

    public function destroy(AssessmentActionRequest $request, Assessment $assessment, EvidenceFile $evidence, #[CurrentUser] User $actor, RemoveEvidence $remove): Response
    {
        $remove->handle($actor, $assessment, $evidence, $request->reason());

        return ApiResponse::noContent();
    }

    public function download(Assessment $assessment, EvidenceFile $evidence, #[CurrentUser] User $actor, IssueEvidenceDownload $issue): JsonResponse
    {
        return ApiResponse::resource(new SignedDownloadResource($issue->handle($actor, $evidence)));
    }
}
