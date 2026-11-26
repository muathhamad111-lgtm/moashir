<?php

declare(strict_types=1);

namespace App\Actions\Insights;

use App\Data\Insights\ExecutiveSummary;
use App\Models\Assessments\Assessment;
use App\Services\Assessments\AssessmentSummaryReader;
use App\Services\Insights\InsightsSubjectLoader;
use App\Support\Insights\ExecutiveSummaryBuilder;
use App\Support\Insights\GapClassifier;
use App\Support\Insights\MaturityBandClassifier;
use App\Support\Insights\ResultDeltaCalculator;

final readonly class GetExecutiveSummary
{
    public function __construct(
        private InsightsSubjectLoader $subjects,
        private GapClassifier $gaps,
        private MaturityBandClassifier $bands,
        private ResultDeltaCalculator $deltas,
        private ExecutiveSummaryBuilder $builder,
        private AssessmentSummaryReader $summaries,
    ) {}

    public function handle(Assessment $assessment): ExecutiveSummary
    {
        $subject = $this->subjects->load($assessment);
        $classes = $this->gaps->classify($subject->tree, $subject->rules->gaps);

        return new ExecutiveSummary(
            $subject,
            $classes,
            $this->bands->profileOf($subject->tree, $subject->rules->bands),
            $this->deltas->compare($subject->tree, $subject->previousTree),
            $this->builder->highlights($classes, $subject->feedback),
            $this->builder->standingOf($subject->result->certificate),
            $this->summaries->currentOf($subject->result->assessment),
        );
    }
}
