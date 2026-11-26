<?php

declare(strict_types=1);

namespace App\Actions\Insights;

use App\Data\Insights\MaturityReport;
use App\Models\Assessments\Assessment;
use App\Services\Insights\InsightsSubjectLoader;
use App\Services\Insights\MaturityTextBookReader;
use App\Support\Insights\MaturityBandClassifier;
use App\Support\Insights\ResultDeltaCalculator;

final readonly class GetMaturityAnalysis
{
    public function __construct(
        private InsightsSubjectLoader $subjects,
        private MaturityBandClassifier $classifier,
        private ResultDeltaCalculator $deltas,
        private MaturityTextBookReader $texts,
    ) {}

    public function handle(Assessment $assessment): MaturityReport
    {
        $subject = $this->subjects->load($assessment);

        return new MaturityReport(
            $subject,
            $this->classifier->profileOf($subject->tree, $subject->rules->bands),
            $this->deltas->compare($subject->tree, $subject->previousTree),
            $this->texts->read(),
        );
    }
}
