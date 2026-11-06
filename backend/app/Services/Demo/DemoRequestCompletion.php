<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Data\Demo\DemoAccreditedResult;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\ResultSnapshotKind;
use App\Models\Assessments\Assessment;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Identity\User;

final readonly class DemoRequestCompletion
{
    public function __construct(private DemoResultScorer $scorer) {}

    public function complete(Assessment $request, DemoAccreditedResult $plan, User $assessor): void
    {
        $this->scorer->score($request, CriteriaVersion::query()->findOrFail($request->criteria_version_id), $plan->profile, ResultSnapshotKind::AccreditedResult, $assessor->id);
        $request->forceFill([
            'status' => AccreditedAssessmentStatus::Completed,
            'submitted_at' => $plan->submittedAt(),
            'completed_at' => $plan->completedAt(),
        ])->save();
    }
}
