<?php

declare(strict_types=1);

namespace App\Http\Resources\Award;

use App\Models\Award\AwardCycle;
use App\Models\Entities\Region;
use App\Support\Entities\EntityPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;

/**
 * @property-read AwardCycle $resource
 */
final class PublicCycleResource extends JsonResource
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
            'is_accepting_applications' => $cycle->acceptsApplicationsAt(Date::now()),
            'submission_start' => $cycle->submission_start->toIso8601String(),
            'submission_end' => $cycle->submission_end->toIso8601String(),
            'committee_start' => $cycle->committee_start->toIso8601String(),
            'committee_end' => $cycle->committee_end->toIso8601String(),
            'announcement_at' => $cycle->announcement_at?->toIso8601String(),
            'conditions' => ['min_entity_age_years' => $cycle->min_entity_age_years],
            'regions' => array_values($cycle->regions->map(static fn (Region $region): array => EntityPresenter::place($region))->all()),
        ];
    }
}
