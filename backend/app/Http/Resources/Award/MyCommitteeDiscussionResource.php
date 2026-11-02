<?php

declare(strict_types=1);

namespace App\Http\Resources\Award;

use App\Enums\Award\CommitteeEntityVisibility;
use App\Models\Award\CommitteeDiscussion;
use App\Models\Award\CommitteeDiscussionEntry;
use App\Support\Award\CommitteeEntityPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;

/**
 * @property-read CommitteeDiscussion $resource
 */
final class MyCommitteeDiscussionResource extends JsonResource
{
    /**
     * @param  list<int>  $memberIds
     */
    public function __construct(CommitteeDiscussion $resource, private readonly CommitteeEntityVisibility $visibility, private readonly array $memberIds)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $discussion = $this->resource;
        $entries = array_values($discussion->currentEntries->map(fn (CommitteeDiscussionEntry $entry): array => [
            'id' => $entry->id,
            'kind' => $entry->kind->value,
            'body' => $entry->body,
            'is_mine' => in_array($entry->cycle_committee_assessor_id, $this->memberIds, true),
            'at' => $entry->created_at->toIso8601String(),
        ])->all());

        return [
            ...CommitteeDiscussionResource::header($discussion),
            'entity' => CommitteeEntityPresenter::present($discussion->assessment, $this->visibility),
            'accepts_entries' => $discussion->acceptsEntriesAt(Date::now()),
            'my_entry' => array_values(array_filter($entries, static fn (array $entry): bool => $entry['is_mine']))[0] ?? null,
            'entries' => $entries,
        ];
    }
}
