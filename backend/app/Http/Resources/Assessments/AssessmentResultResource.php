<?php

declare(strict_types=1);

namespace App\Http\Resources\Assessments;

use App\Data\Assessments\AssessmentResultView;
use App\Data\Assessments\CriteriaNodeNames;
use App\Data\Assessments\FeedbackSheet;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Support\Assessments\PathPresenter;
use App\Support\Assessments\ResultBreakdownPresenter;
use App\Support\Assessments\StatusMask;
use App\Support\Award\AwardOutcomePresenter;
use App\Support\ScalarValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AssessmentResultView $resource
 */
final class AssessmentResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $view = $this->resource;
        $assessment = $view->assessment;
        $identity = [
            'id' => $assessment->id,
            'number' => $assessment->request_number,
            ...PathPresenter::of($assessment->path),
            'status' => StatusMask::statusFor($assessment, $request->user()),
            'is_frozen' => $assessment->isFrozen(),
        ];

        if (! $view->snapshot instanceof AssessmentResultSnapshot || ! $view->names instanceof CriteriaNodeNames) {
            return [...$identity, 'available' => false];
        }

        $breakdown = new ResultBreakdownPresenter($view->snapshot->breakdown, $view->names);
        $subs = $breakdown->subs();

        $result = [
            ...$identity,
            'available' => true,
            'criteria_version_id' => $view->snapshot->criteria_version_id,
            'scale_kind' => $view->snapshot->scale_kind->value,
            'result_version' => $view->snapshot->version,
            'submitted_at' => $assessment->submitted_at?->toIso8601String(),
            'completed_at' => $assessment->completed_at?->toIso8601String(),
            'total' => $view->snapshot->total_score->toDisplay(),
            'max_total' => $view->snapshot->total_weight->toDecimal(),
            'percent' => $view->snapshot->percent->toDisplay(),
            'main' => $breakdown->mains(),
            'sub' => $subs,
            'points' => $breakdown->points(),
        ];

        if ($assessment->isAdvanced()) {
            $result['award'] = AwardOutcomePresenter::present($assessment, $view->ranking);
        }

        return $view->feedback instanceof FeedbackSheet ? [...$result, 'feedback' => $this->feedbackOf($view->feedback, $subs)] : $result;
    }

    /**
     * @param  list<array<string, mixed>>  $subs
     * @return list<array<string, mixed>>
     */
    private function feedbackOf(FeedbackSheet $feedback, array $subs): array
    {
        return array_map(static fn (array $sub): array => [
            'sub_id' => $sub['sub_id'],
            'code' => $sub['code'],
            'name' => $sub['name'],
            'percent' => $sub['percent'] ?? null,
            ...$feedback->fieldsOf(ScalarValue::string($sub['code'])),
        ], $subs);
    }
}
