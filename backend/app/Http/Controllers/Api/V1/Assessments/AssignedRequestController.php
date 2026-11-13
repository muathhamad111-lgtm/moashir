<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assessments;

use App\Actions\Assessments\ListAssignedRequests;
use App\Data\Assessments\PathCount;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Identity\AssessorQualification;
use App\Http\Requests\Assessments\IndexAssignedRequestsRequest;
use App\Http\Resources\Assessments\AssignedRequestResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Services\Assessments\RequestPathCounts;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class AssignedRequestController
{
    public function __invoke(IndexAssignedRequestsRequest $request, #[CurrentUser] User $assessor, ListAssignedRequests $list, RequestPathCounts $counts): JsonResponse
    {
        $qualified = array_map(static fn (AssessorQualification $qualification): AssessmentPath => $qualification->path(), $assessor->qualifications());
        $pathCounts = $counts->forAssessor($assessor, $qualified);
        $path = $request->assessmentPath() ?? ($pathCounts === [] ? AssessmentPath::Accredited : $pathCounts[0]->path);

        return ApiResponse::paginated($list->handle($assessor, $path, $request->tab(), $request->perPage()), AssignedRequestResource::class, [
            'path' => $path->value,
            'path_counts' => array_map(static fn (PathCount $count): array => $count->toArray(), $pathCounts),
        ]);
    }
}
