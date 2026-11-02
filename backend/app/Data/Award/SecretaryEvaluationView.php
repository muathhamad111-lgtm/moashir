<?php

declare(strict_types=1);

namespace App\Data\Award;

use App\Data\Assessments\AssessmentResult;
use App\Data\Assessments\CriteriaNodeNames;

final readonly class SecretaryEvaluationView
{
    public function __construct(public CommitteeScores $scores, public CommitteeNotes $notes, public AssessmentResult $result, public CriteriaNodeNames $names) {}
}
