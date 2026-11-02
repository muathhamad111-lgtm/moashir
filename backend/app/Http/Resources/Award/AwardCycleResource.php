<?php

declare(strict_types=1);

namespace App\Http\Resources\Award;

use App\Models\Award\AwardCycle;
use App\Models\Entities\Region;
use App\Support\Entities\EntityPresenter;
use App\Support\ScalarValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AwardCycle $resource
 */
final class AwardCycleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $cycle = $this->resource;

        return [
            'id' => $cycle->id,
            'name' => $cycle->name->toArray(),
            'year' => $cycle->year,
            'status' => $cycle->status->value,
            'criteria_version_id' => $cycle->criteria_version_id,
            'scale_kind' => $cycle->scale_kind->value,
            'submission_start' => $cycle->submission_start->toIso8601String(),
            'submission_end' => $cycle->submission_end->toIso8601String(),
            'committee_start' => $cycle->committee_start->toIso8601String(),
            'committee_end' => $cycle->committee_end->toIso8601String(),
            'announcement_at' => $cycle->announcement_at?->toIso8601String(),
            'conditions' => [
                'min_entity_age_years' => $cycle->min_entity_age_years,
                'disqualification_score' => $cycle->disqualification_score->toDisplay(),
            ],
            'initial_review_sla_days' => $cycle->initial_review_sla_days,
            'participation_certificate_enabled' => $cycle->participation_certificate_enabled,
            'regions' => array_values($cycle->regions->map(static fn (Region $region): array => EntityPresenter::place($region))->all()),
            'counts' => $this->counts($cycle),
            'opened_at' => $cycle->opened_at?->toIso8601String(),
            'submissions_closed_at' => $cycle->submissions_closed_at?->toIso8601String(),
            'closed_at' => $cycle->closed_at?->toIso8601String(),
            'created_at' => $cycle->created_at->toIso8601String(),
            'updated_at' => $cycle->updated_at->toIso8601String(),
        ];
    }

    /**
     * @return array{requests: int, qualified: int}|null
     */
    private function counts(AwardCycle $cycle): ?array
    {
        $requests = $cycle->getAttribute('requests_count');

        return $requests === null ? null : [
            'requests' => ScalarValue::int($requests),
            'qualified' => ScalarValue::int($cycle->getAttribute('qualified_count')),
        ];
    }
}
