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
            static fn (StatusHistoryEntry $entry): bool => StatusMask::showsHistoryStep($assessment, $request->user(), $entry),
        ));

        return [
            ...new AssessmentResource($assessment)->toArray($request),
            'status_history' => array_map(fn (StatusHistoryEntry $entry): array => $this->historyItem($entry, $request), $visible),
            ...RequestTabs::of($assessment),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function historyItem(StatusHistoryEntry $entry, Request $request): array
    {
        $item = [
            'from' => $this->fromStatusOf($entry, $request),
            'to' => StatusMask::valueFor($this->resource->assessment, $request->user(), $entry->to),
            'verb' => $entry->verb->value,
            'at' => $entry->at->toIso8601String(),
            'actor' => $entry->actor->value,
        ];

        if (! $this->resource->revealsStaffActors()) {
            return $item;
        }

        return [...$item, 'actor_staff' => $this->resource->staffActorOf($entry)?->toArray()];
    }

    /**
     * Entries that move INTO a masked award stage are dropped above, except the owner's submission into it; one that
     * moves OUT of it (a completion request, a withdrawal or a refund) keeps its row, with its origin masked like the
     * current status.
     */
    private function fromStatusOf(StatusHistoryEntry $entry, Request $request): ?string
    {
        if ($entry->from === null) {
            return null;
        }

        return StatusMask::valueFor($this->resource->assessment, $request->user(), $entry->from);
    }
}
