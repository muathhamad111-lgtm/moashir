<?php

declare(strict_types=1);

namespace App\Data\Award;

use App\Data\Assessments\SubmissionContent;
use App\Enums\Assessments\ReviewMode;
use App\Enums\Award\CommitteeEntityVisibility;
use App\Models\Award\SecretaryDecision;

final readonly class CommitteeEvaluationView
{
    /**
     * @param  array<string, mixed>  $criteria
     */
    public function __construct(
        public CommitteeScores $scores,
        public ReviewMode $mode,
        public CommitteeEntityVisibility $visibility,
        public array $criteria,
        public SubmissionContent $content,
        public CommitteeNotes $notes,
        public ?SecretaryDecision $returnNote,
        public ?PriorEvaluation $prior,
    ) {}
}
