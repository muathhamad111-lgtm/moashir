<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Data\Demo\DemoAccreditedResult;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\ResultSnapshotKind;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentPeriod;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Identity\User;
use App\Services\Periods\PeriodLocator;

final readonly class DemoRequestCompletion
{
    public function __construct(private DemoResultScorer $scorer, private PeriodLocator $periods) {}

    public function complete(Assessment $request, DemoAccreditedResult $plan, User $assessor): void
    {
        $this->scorer->score($request, CriteriaVersion::query()->findOrFail($request->criteria_version_id), $plan->profile, ResultSnapshotKind::AccreditedResult, $assessor->id);
        $period = $this->periods->containing($plan->submittedAt());
        $request->forceFill([
            'status' => AccreditedAssessmentStatus::Completed,
            'submitted_at' => $plan->submittedAt(),
            'completed_at' => $plan->completedAt(),
            'period_id' => $period?->id,
            'period_assigned_at' => $period instanceof AssessmentPeriod ? $plan->submittedAt() : null,
        ])->save();
    }
}
