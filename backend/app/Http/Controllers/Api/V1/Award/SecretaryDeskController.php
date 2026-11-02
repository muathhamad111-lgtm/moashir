<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Award;

use App\Actions\Award\ListSecretaryRequests;
use App\Actions\Award\ShowSecretaryEvaluation;
use App\Actions\Award\ShowSecretaryRequest;
use App\Actions\Award\SummarizeSecretaryRequests;
use App\Http\Requests\Award\IndexSecretaryRequestsRequest;
use App\Http\Resources\Award\SecretaryEvaluationResource;
use App\Http\Resources\Award\SecretaryRequestDetailResource;
use App\Http\Resources\Award\SecretaryRequestRowResource;
use App\Http\Responses\ApiResponse;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Award\CommitteeAssignment;
use Illuminate\Http\JsonResponse;

final class SecretaryDeskController
{
    public function index(IndexSecretaryRequestsRequest $request, AwardCycle $cycle, ListSecretaryRequests $list, SummarizeSecretaryRequests $summary): JsonResponse
    {
        $filters = $request->filters();

        return ApiResponse::paginated($list->handle($cycle, $filters), SecretaryRequestRowResource::class, ['counts' => $summary->handle($cycle, $filters)]);
    }

    public function show(AwardCycle $cycle, Assessment $assessment, ShowSecretaryRequest $show): JsonResponse
    {
        return ApiResponse::resource(new SecretaryRequestDetailResource($show->handle($assessment)));
    }

    public function evaluation(AwardCycle $cycle, Assessment $assessment, CommitteeAssignment $assignment, ShowSecretaryEvaluation $show): JsonResponse
    {
        return ApiResponse::resource(new SecretaryEvaluationResource($show->handle($assignment)));
    }
}
