<?php

declare(strict_types=1);

namespace App\Services\Dashboards;

use App\Models\Assessments\AssessmentStatusTransition;
use App\Support\Assessments\PathPresenter;
use App\Support\ScalarValue;

final class ActivityTimeline
{
    /**
     * @return list<array{assessment_id: int, number: string, path: string, path_label: array{ar: string, en: string}|null, verb: string, to_status: string, at: string}>
     */
    public function latest(int $limit): array
    {
        return array_values(AssessmentStatusTransition::query()
            ->join('assessments', 'assessments.id', '=', 'assessment_status_transitions.assessment_id')
            ->orderByDesc('assessment_status_transitions.id')
            ->limit($limit)
            ->get([
                'assessment_status_transitions.assessment_id',
                'assessment_status_transitions.verb',
                'assessment_status_transitions.to_status',
                'assessment_status_transitions.created_at',
                'assessments.request_number',
                'assessments.path',
            ])
            ->map(static fn (AssessmentStatusTransition $transition): array => [
                'assessment_id' => $transition->assessment_id,
                'number' => ScalarValue::string($transition->getAttribute('request_number')),
                ...PathPresenter::ofValue(ScalarValue::string($transition->getAttribute('path'))),
                'verb' => $transition->verb->value,
                'to_status' => $transition->to_status,
                'at' => $transition->created_at->toIso8601String(),
            ])
            ->all());
    }
}
