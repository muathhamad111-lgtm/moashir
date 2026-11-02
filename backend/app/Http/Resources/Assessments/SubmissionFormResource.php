<?php

declare(strict_types=1);

namespace App\Http\Resources\Assessments;

use App\Data\Assessments\SubmissionForm;
use App\Support\Assessments\CompletionRequestPresenter;
use App\Support\Assessments\EvidencePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read SubmissionForm $resource
 */
final class SubmissionFormResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $form = $this->resource;
        $subs = $form->evidence->subs;
        $criteriaUpdate = $form->criteriaUpdate;

        return [
            'assessment' => new AssessmentResource($form->cv->assessment)->toArray($request),
            'criteria' => $form->criteria,
            'cv' => (object) $form->cv->bodiesBySubId(),
            'evidence' => EvidencePresenter::list($form->evidence->evidence, $subs),
            'missing' => $form->gaps->toArray(),
            'completion_request' => CompletionRequestPresenter::present($form->completionRequest, $subs),
            'upload_limits' => $form->uploadLimits->toArray(),
            'criteria_update' => $criteriaUpdate === null ? null : new CriteriaMoveSummaryResource($criteriaUpdate)->toArray($request),
        ];
    }
}
