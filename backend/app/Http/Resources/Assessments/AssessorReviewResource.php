<?php

declare(strict_types=1);

namespace App\Http\Resources\Assessments;

use App\Data\Assessments\AssessorReview;
use App\Data\Assessments\FeedbackView;
use App\Models\Assessments\Assessment;
use App\Support\Assessments\CompletionRequestPresenter;
use App\Support\Assessments\EvidencePresenter;
use App\Support\Assessments\PathPresenter;
use App\Support\Assessments\ReviewClockPresenter;
use App\Support\Entities\EntityPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AssessorReview $resource
 */
final class AssessorReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $review = $this->resource;
        $assessment = $review->scores->assessment;
        $subs = $review->evidence->subs;

        return [
            ...new AssessmentScoresResource($review->scores)->toArray($request),
            'mode' => $review->mode->value,
            ...ReviewClockPresenter::of($assessment),
            'entity' => [...EntityPresenter::summary($assessment->entity), ...EntityPresenter::profile($assessment->entity)],
            'criteria' => $review->criteria,
            'cv' => (object) $review->cv->bodiesBySubId(),
            'evidence' => EvidencePresenter::list($review->evidence->evidence, $subs),
            'feedback' => (object) new FeedbackView($assessment, $review->feedback, $subs)->bySubId(),
            'completion_request' => CompletionRequestPresenter::present($review->completionRequest, $subs),
            'context' => array_map(static fn (Assessment $other): array => [
                'id' => $other->id,
                'number' => $other->request_number,
                ...PathPresenter::of($other->path),
                'completed_at' => $other->completed_at?->toIso8601String(),
                'percent' => $other->currentResult?->percent->toDisplay(),
            ], $review->context),
        ];
    }
}
