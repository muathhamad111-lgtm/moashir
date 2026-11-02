<?php

declare(strict_types=1);

namespace App\Http\Resources\Assessments;

use App\Data\Assessments\RequestDetail;
use App\Models\Assessments\AssessmentAssignment;
use App\Models\Assessments\AssessmentCompletionRequest;
use App\Support\Assessments\CompletionRequestPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read RequestDetail $resource
 */
final class RequestDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $detail = $this->resource;
        $assessment = $detail->record->assessment;

        return [
            ...new RequestQueueItemResource($assessment)->toArray($request),
            'payment' => ['paid' => $detail->isPaid],
            'record' => new AssessmentRecordResource($detail->record)->toArray($request),
            'assignment_history' => array_map(fn (AssessmentAssignment $assignment): array => $this->assignmentOf($assignment, $detail->revealsStaffIdentities), $detail->assignments),
            'completion_requests' => array_map(
                static fn (AssessmentCompletionRequest $completion): ?array => CompletionRequestPresenter::present($completion, null),
                $detail->completionRequests,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function assignmentOf(AssessmentAssignment $assignment, bool $revealsIdentities): array
    {
        $item = [
            'id' => $assignment->id,
            'assessor' => ['id' => $assignment->assessor_user_id, 'name' => $assignment->assessor->name],
            'status' => $assignment->status->value,
            'assigned_at' => $assignment->created_at->toIso8601String(),
            'started_at' => $assignment->started_at?->toIso8601String(),
            'ended_at' => $assignment->ended_at?->toIso8601String(),
        ];

        if (! $revealsIdentities) {
            return $item;
        }

        return [
            ...$item,
            'assigned_by' => $assignment->assignedBy === null ? null : ['id' => $assignment->assignedBy->id, 'name' => $assignment->assignedBy->isSuperAdmin() ? null : $assignment->assignedBy->name],
            'reason' => $assignment->reason,
            'end_reason' => $assignment->end_reason,
        ];
    }
}
