<?php

declare(strict_types=1);

namespace App\Http\Resources\Award;

use App\Models\Award\CommitteeDiscussion;
use App\Models\Award\CommitteeDiscussionEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read CommitteeDiscussion $resource
 */
final class CommitteeDiscussionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $discussion = $this->resource;

        return [
            ...self::header($discussion),
            'opened_by' => ['id' => $discussion->opened_by_user_id, 'name' => $discussion->relationLoaded('openedBy') ? $discussion->openedBy->name : null],
            'summary' => $discussion->summary,
            'entries' => array_values($discussion->currentEntries->map(static fn (CommitteeDiscussionEntry $entry): array => [
                'id' => $entry->id,
                'member' => [
                    'id' => $entry->cycle_committee_assessor_id,
                    'name' => $entry->relationLoaded('member') && $entry->member->relationLoaded('assessor') ? $entry->member->assessor->name : null,
                ],
                'kind' => $entry->kind->value,
                'body' => $entry->body,
                'at' => $entry->created_at->toIso8601String(),
            ])->all()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function header(CommitteeDiscussion $discussion): array
    {
        return [
            'id' => $discussion->id,
            'assessment_id' => $discussion->assessment_id,
            'mode' => $discussion->mode->value,
            'status' => $discussion->status->value,
            'deadline_at' => $discussion->deadline_at?->toIso8601String(),
            'agreed_outcome' => $discussion->agreed_outcome,
            'opened_at' => $discussion->created_at->toIso8601String(),
            'closed_at' => $discussion->closed_at?->toIso8601String(),
        ];
    }
}
