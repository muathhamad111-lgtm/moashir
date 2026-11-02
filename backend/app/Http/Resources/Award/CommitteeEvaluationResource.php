<?php

declare(strict_types=1);

namespace App\Http\Resources\Award;

use App\Data\Award\CommitteeEvaluationView;
use App\Data\Award\PriorEvaluation;
use App\Models\Award\SecretaryDecision;
use App\Support\Assessments\EvidencePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read CommitteeEvaluationView $resource
 */
final class CommitteeEvaluationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $view = $this->resource;
        $assignment = $view->scores->assignment;
        $content = $view->content;

        return [
            'assignment' => new CommitteeAssignmentRowResource($assignment, $view->visibility)->toArray($request),
            'mode' => $view->mode->value,
            ...new CommitteeScoresResource($view->scores)->toArray($request),
            'criteria' => $view->criteria,
            'cv' => (object) $content->cv->bodiesBySubId(),
            'evidence' => EvidencePresenter::list($content->evidence->evidence, $content->subs),
            'notes' => $view->notes->toArray(),
            'return_note' => $view->returnNote instanceof SecretaryDecision ? ['note' => $view->returnNote->note, 'returned_at' => $view->returnNote->created_at->toIso8601String()] : null,
            'prior' => $view->prior instanceof PriorEvaluation ? $this->prior($view->prior, $request) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function prior(PriorEvaluation $prior, Request $request): array
    {
        $scores = new CommitteeScoresResource($prior->scores)->toArray($request);

        return [
            'scores' => $scores['scores'],
            'progress' => $scores['progress'],
            'percent' => $scores['percent'],
            'status' => $scores['status'],
            'notes' => $prior->notes->toArray(),
            'view_only' => true,
        ];
    }
}
