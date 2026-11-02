<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\AssessmentResultView;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Models\Criteria\CriteriaVersion;

final readonly class InitialResultReader
{
    public function __construct(private CriteriaNodeNamesReader $names, private FeedbackSheetReader $feedback) {}

    public function read(Assessment $assessment): AssessmentResultView
    {
        $snapshot = $assessment->loadMissing('currentResult')->currentResult;

        if (! $snapshot instanceof AssessmentResultSnapshot) {
            return new AssessmentResultView($assessment);
        }

        return new AssessmentResultView(
            $assessment,
            $snapshot,
            $this->names->of(CriteriaVersion::query()->findOrFail($snapshot->criteria_version_id)),
            $this->feedback->read($assessment),
        );
    }
}
