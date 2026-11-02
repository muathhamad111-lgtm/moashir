<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\AssessmentResult;
use App\Enums\Assessments\ResultSnapshotKind;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Support\RequestContext;
use App\Support\ScalarValue;

final readonly class ResultSnapshotRecorder
{
    public function __construct(private RequestContext $context) {}

    public function record(Assessment $assessment, AssessmentResult $result, ResultSnapshotKind $kind, ?int $computedBy): AssessmentResultSnapshot
    {
        $latest = AssessmentResultSnapshot::query()->where('assessment_id', $assessment->id)->max('version');

        $snapshot = AssessmentResultSnapshot::query()->create([
            'assessment_id' => $assessment->id,
            'version' => ScalarValue::int($latest) + 1,
            'kind' => $kind,
            'criteria_version_id' => $assessment->criteria_version_id,
            'scale_kind' => $assessment->scale_kind,
            'total_score' => $result->total,
            'total_weight' => $result->totalWeight,
            'percent' => $result->percent,
            'breakdown' => $result->breakdown(),
            'computed_by_user_id' => $computedBy,
            'reason' => $this->context->reason(),
        ]);

        $assessment->forceFill(['current_result_snapshot_id' => $snapshot->id]);
        $assessment->setRelation('currentResult', $snapshot);

        return $snapshot;
    }
}
