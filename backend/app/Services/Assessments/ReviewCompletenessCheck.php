<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\ScoredAssessment;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Models\Assessments\Assessment;

final readonly class ReviewCompletenessCheck
{
    public function __construct(
        private ScoringTreeBuilder $trees,
        private ScoreSheetReader $scores,
    ) {}

    public function assertComplete(Assessment $locked): ScoredAssessment
    {
        $tree = $this->trees->build($locked->criteriaVersion);
        $sheet = $this->scores->read($locked, $tree);
        $missing = $tree->unscoredUnits($sheet);

        if ($missing !== []) {
            throw AssessmentRejected::scoresIncomplete($missing);
        }

        return new ScoredAssessment($tree, $sheet);
    }
}
