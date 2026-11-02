<?php

declare(strict_types=1);

namespace App\Http\Resources\Award;

use App\Data\Award\SecretaryEvaluationView;
use App\Support\Assessments\ResultBreakdownPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read SecretaryEvaluationView $resource
 */
final class SecretaryEvaluationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $view = $this->resource;
        $assignment = $view->scores->assignment;
        $member = $assignment->member;
        $breakdown = new ResultBreakdownPresenter($view->result->breakdown(), $view->names);

        return [
            'id' => $assignment->id,
            'member' => ['id' => $member->id, 'user' => ['id' => $member->assessor_user_id, 'name' => $member->assessor->name], 'status' => $member->status->value],
            'is_substitute' => $assignment->replaces_assignment_id !== null,
            'submitted_at' => $assignment->submitted_at?->toIso8601String(),
            ...new CommitteeScoresResource($view->scores)->toArray($request),
            'notes' => $view->notes->toArray(),
            'result' => [
                'total' => $view->result->total->toDisplay(),
                'max_total' => $view->result->totalWeight->toDecimal(),
                'percent' => $view->result->percent->toDisplay(),
                'main' => $breakdown->mains(),
                'sub' => $breakdown->subs(),
                'points' => $breakdown->points(),
            ],
        ];
    }
}
