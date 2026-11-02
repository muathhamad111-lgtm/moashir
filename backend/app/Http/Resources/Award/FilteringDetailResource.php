<?php

declare(strict_types=1);

namespace App\Http\Resources\Award;

use App\Data\Award\FilteringDetail;
use App\Http\Resources\Assessments\AssessmentResultResource;
use App\Http\Resources\Assessments\AssessorReviewResource;
use App\Models\Identity\User;
use App\Support\Award\FilteringDecisionPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read FilteringDetail $resource
 */
final class FilteringDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $detail = $this->resource;
        $assessment = $detail->result->assessment;
        $cycle = $assessment->awardCycle;

        return [
            'row' => new FilteringRowResource($assessment->loadMissing(['region', 'latestFilteringDecision.decidedBy']))->toArray($request),
            'disqualification_score' => $cycle?->disqualification_score->toDisplay(),
            'assessor' => $detail->assessor instanceof User ? ['id' => $detail->assessor->id, 'name' => $detail->assessor->name] : null,
            'review' => new AssessorReviewResource($detail->review)->toArray($request),
            'result' => new AssessmentResultResource($detail->result)->toArray($request),
            'decisions' => array_map(FilteringDecisionPresenter::present(...), $detail->decisions),
        ];
    }
}
