<?php

declare(strict_types=1);

namespace App\Data\Insights;

use App\Data\Assessments\FeedbackSheet;

final readonly class InsightsSubject
{
    public function __construct(
        public ResultCandidate $result,
        public ?ResultCandidate $previous,
        public ResultTree $tree,
        public ?ResultTree $previousTree,
        public FeedbackSheet $feedback,
        public InsightsRules $rules,
    ) {}
}
