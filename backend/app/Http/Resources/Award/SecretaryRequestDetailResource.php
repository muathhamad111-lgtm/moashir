<?php

declare(strict_types=1);

namespace App\Http\Resources\Award;

use App\Data\Award\SecretaryRequestDetail;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CommitteeAverage;
use App\Models\Award\CommitteeDiscussion;
use App\Models\Award\SecretaryDecision;
use App\Models\Entities\Region;
use App\Support\Entities\EntityPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read SecretaryRequestDetail $resource
 */
final class SecretaryRequestDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $detail = $this->resource;
        $assessment = $detail->assessment;
        $initial = $assessment->currentResult;
        $counted = array_map(static fn (CommitteeAssignment $row): int => $row->id, $detail->tally->counted);

        return [
            'id' => $assessment->id,
            'number' => $assessment->request_number,
            'status' => $assessment->status->value,
            'entity' => EntityPresenter::summary($assessment->entity),
            'region' => $assessment->region instanceof Region ? EntityPresenter::place($assessment->region) : null,
            'initial_result' => $initial instanceof AssessmentResultSnapshot ? ['total' => $initial->total_score->toDisplay(), 'max_total' => $initial->total_weight->toDecimal(), 'percent' => $initial->percent->toDisplay()] : null,
            'average' => $assessment->committeeAverage instanceof CommitteeAverage ? SecretaryRequestRowResource::average($assessment->committeeAverage) : null,
            'all_submitted' => $detail->tally->isComplete(),
            'pending_assignment_ids' => $detail->tally->pendingIds(),
            'pending_substitution_ids' => $detail->tally->pendingSubstituteIds(),
            'evaluations' => array_map(static fn (CommitteeAssignment $row): array => self::evaluation($row, in_array($row->id, $counted, true)), $detail->assignments),
            'decisions' => array_map(static fn (SecretaryDecision $decision): array => [
                'id' => $decision->id,
                'kind' => $decision->kind->value,
                'note' => $decision->note,
                'committee_average_id' => $decision->committee_average_id,
                'decided_by' => ['id' => $decision->decided_by_user_id, 'name' => $decision->decidedBy->name],
                'decided_at' => $decision->created_at->toIso8601String(),
            ], $detail->decisions),
            'discussions' => array_map(static fn (CommitteeDiscussion $discussion): array => new CommitteeDiscussionResource($discussion)->toArray($request), $detail->discussions),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function evaluation(CommitteeAssignment $row, bool $counted): array
    {
        $member = $row->member;

        return [
            'id' => $row->id,
            'member' => ['id' => $member->id, 'user' => ['id' => $member->assessor_user_id, 'name' => $member->assessor->name], 'status' => $member->status->value],
            'status' => $row->status->value,
            'counted' => $counted,
            'is_substitute' => $row->replaces_assignment_id !== null,
            'replaces_assignment_id' => $row->replaces_assignment_id,
            'substitute_approved_at' => $row->substitute_approved_at?->toIso8601String(),
            'percent' => $row->percent?->toDisplay(),
            'started_at' => $row->started_at?->toIso8601String(),
            'submitted_at' => $row->submitted_at?->toIso8601String(),
            'returned_at' => $row->returned_at?->toIso8601String(),
            'voided_at' => $row->voided_at?->toIso8601String(),
            'void_reason' => $row->void_reason?->value,
        ];
    }
}
