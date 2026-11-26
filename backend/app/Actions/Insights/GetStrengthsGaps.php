<?php

declare(strict_types=1);

namespace App\Actions\Insights;

use App\Data\Insights\StrengthsGapsReport;
use App\Models\Assessments\Assessment;
use App\Services\Insights\InsightsSubjectLoader;
use App\Support\Insights\GapClassifier;

final readonly class GetStrengthsGaps
{
    public function __construct(private InsightsSubjectLoader $subjects, private GapClassifier $classifier) {}

    public function handle(Assessment $assessment): StrengthsGapsReport
    {
        $subject = $this->subjects->load($assessment);

        return new StrengthsGapsReport($subject, $this->classifier->classify($subject->tree, $subject->rules->gaps));
    }
}
