<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Award;

use App\Actions\Award\ListQualificationCandidates;
use App\Actions\Award\QualifyEligibleRequests;
use App\Http\Requests\Award\QualifyEligibleRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class BulkQualificationController
{
    public function show(AwardCycle $cycle, #[CurrentUser] User $actor, ListQualificationCandidates $candidates): JsonResponse
    {
        $found = $candidates->handle($actor, $cycle);

        return ApiResponse::data([
            'count' => count($found->eligible),
            'threshold_percent' => $cycle->disqualification_score->toDisplay(),
            'own_assessments' => $found->ownAssessments,
            'requests' => array_map($this->row(...), $found->eligible),
        ]);
    }

    public function store(QualifyEligibleRequest $request, AwardCycle $cycle, #[CurrentUser] User $actor, QualifyEligibleRequests $qualify): JsonResponse
    {
        $outcome = $qualify->handle($actor, $cycle, $request->reason());

        return ApiResponse::data([
            'qualified' => count($outcome->qualifiedIds),
            'qualified_ids' => $outcome->qualifiedIds,
            'skipped' => $outcome->skipped,
        ]);
    }

    /**
     * @return array{id: int, number: string, initial_percent: string|null}
     */
    private function row(Assessment $assessment): array
    {
        $result = $assessment->currentResult;

        return [
            'id' => $assessment->id,
            'number' => $assessment->request_number,
            'initial_percent' => $result instanceof AssessmentResultSnapshot ? $result->percent->toDisplay() : null,
        ];
    }
}
