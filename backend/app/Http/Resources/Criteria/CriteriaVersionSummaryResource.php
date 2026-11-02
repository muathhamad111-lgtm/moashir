<?php

declare(strict_types=1);

namespace App\Http\Resources\Criteria;

use App\Data\Criteria\CriteriaVersionSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read CriteriaVersionSummary $resource
 */
final class CriteriaVersionSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...new CriteriaVersionResource($this->resource->version)->toArray($request),
            'bound_assessments' => $this->resource->boundAssessments->total,
            'bound_open_assessments' => $this->resource->boundAssessments->open,
        ];
    }
}
