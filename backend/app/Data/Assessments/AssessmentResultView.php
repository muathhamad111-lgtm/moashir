<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Models\Award\AwardRanking;

final readonly class AssessmentResultView
{
    public function __construct(
        public Assessment $assessment,
        public ?AssessmentResultSnapshot $snapshot = null,
        public ?CriteriaNodeNames $names = null,
        public ?FeedbackSheet $feedback = null,
        public ?AwardRanking $ranking = null,
    ) {}

    public function isAvailable(): bool
    {
        return $this->snapshot instanceof AssessmentResultSnapshot && $this->names instanceof CriteriaNodeNames;
    }
}
