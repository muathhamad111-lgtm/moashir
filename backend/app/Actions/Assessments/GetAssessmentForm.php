<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\AssessmentForm;
use App\Data\Assessments\AssessmentScores;
use App\Models\Assessments\Assessment;
use App\Services\Assessments\CriteriaMoveSummaryReader;
use App\Services\Assessments\ScoreSheetReader;
use App\Services\Assessments\ScoringTreeBuilder;
use App\Services\Criteria\CriteriaTreeSerializer;

final readonly class GetAssessmentForm
{
    public function __construct(
        private ScoringTreeBuilder $trees,
        private CriteriaTreeSerializer $serializer,
        private ScoreSheetReader $reader,
        private CriteriaMoveSummaryReader $moves,
    ) {}

    public function handle(Assessment $assessment): AssessmentForm
    {
        $version = $assessment->criteriaVersion;
        $tree = $this->trees->build($version);

        return new AssessmentForm(
            $this->serializer->serialize($version),
            new AssessmentScores($assessment, $tree, $this->reader->read($assessment, $tree)),
            $this->moves->latestFor($assessment),
        );
    }
}
