<?php

declare(strict_types=1);

namespace App\Http\Resources\Award;

use App\Enums\Award\CommitteeEntityVisibility;
use App\Models\Award\CommitteeAssignment;
use App\Support\Award\CommitteeEntityPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;

/**
 * @property-read CommitteeAssignment $resource
 */
final class CommitteeAssignmentRowResource extends JsonResource
{
    public function __construct(CommitteeAssignment $resource, private readonly CommitteeEntityVisibility $visibility)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $assignment = $this->resource;
        $cycle = $assignment->awardCycle;

        return [
            'id' => $assignment->id,
            'assessment_id' => $assignment->assessment_id,
            'cycle' => ['id' => $cycle->id, 'name' => $cycle->name->toArray(), 'status' => $cycle->status->value],
            'entity' => CommitteeEntityPresenter::present($assignment->assessment, $this->visibility),
            'status' => $assignment->status->value,
            'membership_status' => $assignment->member->status->value,
            'is_substitute' => $assignment->replaces_assignment_id !== null,
            'substitute_approved_at' => $assignment->substitute_approved_at?->toIso8601String(),
            'due_at' => $cycle->committee_end->toIso8601String(),
            'is_overdue' => $assignment->isOverdue(Date::now(), $cycle->committee_end),
            'percent' => $assignment->percent?->toDisplay(),
            'started_at' => $assignment->started_at?->toIso8601String(),
            'submitted_at' => $assignment->submitted_at?->toIso8601String(),
            'returned_at' => $assignment->returned_at?->toIso8601String(),
        ];
    }
}
