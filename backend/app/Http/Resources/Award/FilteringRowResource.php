<?php

declare(strict_types=1);

namespace App\Http\Resources\Award;

use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Models\Award\AwardCycle;
use App\Models\Award\FilteringDecision;
use App\Models\Entities\Region;
use App\Support\Award\FilteringDecisionPresenter;
use App\Support\Entities\EntityPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Assessment $resource
 */
final class FilteringRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $assessment = $this->resource;
        $snapshot = $assessment->currentResult;
        $decision = $assessment->latestFilteringDecision;

        return [
            'id' => $assessment->id,
            'number' => $assessment->request_number,
            'status' => $assessment->status->value,
            'is_frozen' => $assessment->isFrozen(),
            'entity' => EntityPresenter::summary($assessment->entity),
            'region' => $assessment->region instanceof Region ? EntityPresenter::place($assessment->region) : null,
            'initial_result' => $snapshot instanceof AssessmentResultSnapshot ? [
                'total' => $snapshot->total_score->toDisplay(),
                'max_total' => $snapshot->total_weight->toDecimal(),
                'percent' => $snapshot->percent->toDisplay(),
            ] : null,
            'meets_threshold' => $this->meetsThreshold($snapshot, $assessment->awardCycle),
            'initial_assessed_at' => $snapshot?->created_at->toIso8601String(),
            'submitted_at' => $assessment->submitted_at?->toIso8601String(),
            'decision' => $decision instanceof FilteringDecision ? FilteringDecisionPresenter::present($decision) : null,
        ];
    }

    private function meetsThreshold(?AssessmentResultSnapshot $snapshot, ?AwardCycle $cycle): ?bool
    {
        if (! $snapshot instanceof AssessmentResultSnapshot || ! $cycle instanceof AwardCycle) {
            return null;
        }

        return $snapshot->percent->tenThousandths >= $cycle->disqualification_score->tenThousandths;
    }
}
