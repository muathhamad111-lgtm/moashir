<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\AssessmentResultView;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Models\Criteria\CriteriaVersion;
use App\Services\Assessments\CriteriaNodeNamesReader;
use App\Services\Assessments\FeedbackSheetReader;

final readonly class GetAssessmentResult
{
    public function __construct(private CriteriaNodeNamesReader $names, private FeedbackSheetReader $feedback) {}

    public function handle(Assessment $assessment): AssessmentResultView
    {
        $snapshot = $assessment->loadMissing('currentResult')->currentResult;

        if (! $snapshot instanceof AssessmentResultSnapshot || ! $assessment->exposesResult()) {
            return new AssessmentResultView($assessment);
        }

        $version = CriteriaVersion::query()->findOrFail($snapshot->criteria_version_id);

        return new AssessmentResultView(
            $assessment,
            $snapshot,
            $this->names->of($version),
            $assessment->path->isReviewed() ? $this->feedback->read($assessment) : null,
            $assessment->isAdvanced() ? $assessment->loadMissing(['ranking', 'region'])->ranking : null,
        );
    }
}
