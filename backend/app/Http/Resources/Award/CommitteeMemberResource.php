<?php

declare(strict_types=1);

namespace App\Http\Resources\Award;

use App\Enums\Award\CommitteeAssignmentStatus;
use App\Models\Award\CycleCommitteeAssessor;
use App\Support\ScalarValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read CycleCommitteeAssessor $resource
 */
final class CommitteeMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $member = $this->resource;
        $progress = [];

        foreach (CommitteeAssignmentStatus::cases() as $status) {
            $progress[$status->value] = ScalarValue::int($member->getAttribute($status->value.'_count'));
        }

        return [
            'id' => $member->id,
            'user' => ['id' => $member->assessor_user_id, 'name' => $member->relationLoaded('assessor') ? $member->assessor->name : null],
            'status' => $member->status->value,
            'is_substitute' => $member->isSubstitute(),
            'replaces_id' => $member->replaces_id,
            'replaced_by_id' => $member->relationLoaded('replacedBy') ? $member->replacedBy?->id : null,
            'appointed_at' => $member->created_at->toIso8601String(),
            'disabled_at' => $member->disabled_at?->toIso8601String(),
            'disabled_reason' => $member->disabled_reason,
            'removed_at' => $member->removed_at?->toIso8601String(),
            'progress' => $progress,
        ];
    }
}
