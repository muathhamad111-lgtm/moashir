<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Award\CommitteeScores;
use App\Data\Award\SecretaryEvaluationView;
use App\Models\Award\CommitteeAssignment;
use App\Services\Assessments\CriteriaNodeNamesReader;
use App\Services\Assessments\ScoringEngine;
use App\Services\Assessments\ScoringTreeBuilder;
use App\Services\Award\CommitteeNotesReader;
use App\Services\Award\CommitteeScoreReader;

final readonly class ShowSecretaryEvaluation
{
    public function __construct(
        private ScoringTreeBuilder $trees,
        private CommitteeScoreReader $scores,
        private CommitteeNotesReader $notes,
        private ScoringEngine $engine,
        private CriteriaNodeNamesReader $names,
    ) {}

    public function handle(CommitteeAssignment $assignment): SecretaryEvaluationView
    {
        $assignment->loadMissing(['assessment.criteriaVersion', 'member.assessor']);
        $assessment = $assignment->assessment;
        $tree = $this->trees->build($assessment->criteriaVersion);
        $sheet = $this->scores->read($assignment, $tree, $assessment->scale_kind);

        return new SecretaryEvaluationView(
            new CommitteeScores($assignment, $tree, $sheet),
            $this->notes->read($assignment),
            $this->engine->compute($tree, $sheet),
            $this->names->of($assessment->criteriaVersion),
        );
    }
}
