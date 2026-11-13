<?php

declare(strict_types=1);

namespace App\Http\Resources\Assessments;

use App\Data\Assessments\AssessmentRecord;
use App\Data\Assessments\StatusHistoryEntry;
use App\Support\Assessments\StatusMask;
use App\Support\Paths\RequestTabs;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AssessmentRecord $resource
 */
final class AssessmentRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $assessment = $this->resource->assessment;
        $visible = array_values(array_filter(
            $this->resource->statusHistory,
            static fn (StatusHistoryEntry $entry): bool => ! StatusMask::hidesFrom($assessment, $request->user(), $entry->to),
        ));

        return [
            ...new AssessmentResource($assessment)->toArray($request),
            'status_history' => array_map($this->historyItem(...), $visible),
            ...RequestTabs::of($assessment),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function historyItem(StatusHistoryEntry $entry): array
    {
        $item = [
            'from' => $entry->from,
            'to' => $entry->to,
            'verb' => $entry->verb->value,
            'at' => $entry->at->toIso8601String(),
            'actor' => $entry->actor->value,
        ];

        if (! $this->resource->revealsStaffActors()) {
            return $item;
        }

        return [...$item, 'actor_staff' => $this->resource->staffActorOf($entry)?->toArray()];
    }
}
