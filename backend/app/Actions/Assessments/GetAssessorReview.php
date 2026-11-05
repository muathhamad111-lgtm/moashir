<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\AssessmentScores;
use App\Data\Assessments\AssessorReview;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\ReviewMode;
use App\Enums\Identity\PermissionKey;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentAssignment;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentContextReader;
use App\Services\Assessments\FeedbackSheetReader;
use App\Services\Assessments\ScoreSheetReader;
use App\Services\Assessments\ScoringTreeBuilder;
use App\Services\Assessments\SubmissionContentReader;
use App\Services\Criteria\CriteriaTreeSerializer;

final readonly class GetAssessorReview
{
    public function __construct(
        private ScoringTreeBuilder $trees,
        private CriteriaTreeSerializer $serializer,
        private ScoreSheetReader $scores,
        private SubmissionContentReader $content,
        private FeedbackSheetReader $feedback,
        private AssessmentContextReader $context,
    ) {}

    public function handle(User $viewer, Assessment $assessment): AssessorReview
    {
        $assessment->loadMissing(['entity.region', 'entity.city', 'entity.owner', 'activeAssignment', 'openCompletionRequest']);
        $version = $assessment->criteriaVersion;
        $tree = $this->trees->build($version);
        $content = $this->content->forReviewer($assessment);

        return new AssessorReview(
            $this->modeFor($viewer, $assessment),
            $this->serializer->serialize($version),
            new AssessmentScores($assessment, $tree, $this->scores->read($assessment, $tree)),
            $content->cv,
            $content->evidence,
            $this->feedback->read($assessment),
            $assessment->openCompletionRequest,
            $this->context->for($viewer, $assessment),
        );
    }

    private function modeFor(User $viewer, Assessment $assessment): ReviewMode
    {
        $assignment = $assessment->activeAssignment;
        $isAssignee = $assignment instanceof AssessmentAssignment && $assignment->isHeldBy($viewer->id);

        return $this->acceptsReviewWork($assessment) && ($viewer->isSuperAdmin() || ($isAssignee && $viewer->can(PermissionKey::AssessmentScoreManage->value)))
            ? ReviewMode::Edit
            : ReviewMode::ReadOnly;
    }

    private function acceptsReviewWork(Assessment $assessment): bool
    {
        return $assessment->status instanceof AdvancedAssessmentStatus ? $assessment->status->hasAssessor() : $assessment->isOpen();
    }
}
