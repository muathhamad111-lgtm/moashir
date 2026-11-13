<?php

declare(strict_types=1);

namespace App\Http\Resources\Assessments;

use App\Models\Assessments\AssessmentSummary;
use App\Support\Assessments\AssessmentSummaryPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AssessmentSummary|null $resource
 */
final class AssessmentSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['summary' => AssessmentSummaryPresenter::present($this->resource)];
    }
}
