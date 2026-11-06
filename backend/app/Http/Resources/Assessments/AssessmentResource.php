<?php

declare(strict_types=1);

namespace App\Http\Resources\Assessments;

use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Support\Assessments\PathPresenter;
use App\Support\Assessments\StatusMask;
use App\Support\Entities\EntityPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Assessment $resource
 */
final class AssessmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $assessment = $this->resource;

        return [
            'id' => $assessment->id,
            'number' => $assessment->request_number,
            ...PathPresenter::of($assessment->path),
            'status' => StatusMask::statusFor($assessment, $request->user()),
            'is_open' => StatusMask::isOpenFor($assessment, $request->user()),
            'is_frozen' => $assessment->isFrozen(),
            'criteria_update_in_progress' => $assessment->awaitsCriteriaUpdate(),
            'criteria_version_id' => $assessment->criteria_version_id,
            'scale_kind' => $assessment->scale_kind->value,
            'award_cycle_id' => $assessment->award_cycle_id,
            'entity' => EntityPresenter::summary($assessment->entity),
            'result' => $assessment->exposesResult() ? $this->result($assessment->currentResult) : null,
            'created_at' => $assessment->created_at->toIso8601String(),
            'updated_at' => $assessment->updated_at->toIso8601String(),
            'submitted_at' => $assessment->submitted_at?->toIso8601String(),
            'completed_at' => $assessment->completed_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{total: string, max_total: string, percent: string}|null
     */
    private function result(?AssessmentResultSnapshot $snapshot): ?array
    {
        return $snapshot instanceof AssessmentResultSnapshot ? [
            'total' => $snapshot->total_score->toDisplay(),
            'max_total' => $snapshot->total_weight->toDecimal(),
            'percent' => $snapshot->percent->toDisplay(),
        ] : null;
    }
}
