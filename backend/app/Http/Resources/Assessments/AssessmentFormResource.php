<?php

declare(strict_types=1);

namespace App\Http\Resources\Assessments;

use App\Data\Assessments\AssessmentForm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AssessmentForm $resource
 */
final class AssessmentFormResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $criteriaUpdate = $this->resource->criteriaUpdate;

        return [
            ...new AssessmentScoresResource($this->resource->scores)->toArray($request),
            'criteria' => $this->resource->criteria,
            'criteria_update' => $criteriaUpdate === null ? null : new CriteriaMoveSummaryResource($criteriaUpdate)->toArray($request),
        ];
    }
}
