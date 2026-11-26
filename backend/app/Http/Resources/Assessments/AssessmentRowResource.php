<?php

declare(strict_types=1);

namespace App\Http\Resources\Assessments;

use App\Data\Assessments\MissedPeriodRow;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Periods\EntityPeriodState;
use App\Enums\Periods\PeriodAttribution;
use App\Models\Assessments\Assessment;
use App\Support\Assessments\PathPresenter;
use App\Support\Periods\PeriodPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Assessment|MissedPeriodRow $resource
 */
final class AssessmentRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $row = $this->resource;

        if ($row instanceof Assessment) {
            return new AssessmentResource($row)->toArray($request);
        }

        return [
            'row_type' => 'missed_period',
            'row_key' => 'period-'.$row->period->id,
            'id' => null,
            'number' => null,
            ...PathPresenter::of(AssessmentPath::Accredited),
            'status' => null,
            'period_status' => EntityPeriodState::Missed->value,
            'period_state' => PeriodAttribution::Assigned->value,
            'period' => PeriodPresenter::period($row->period),
            'period_label' => $row->period->label()->toArray(),
        ];
    }
}
