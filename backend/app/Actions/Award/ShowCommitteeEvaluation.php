<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Award\CommitteeEvaluationView;
use App\Data\Award\CommitteeScores;
use App\Data\Award\PriorEvaluation;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\ReviewMode;
use App\Enums\Award\SecretaryDecisionKind;
use App\Models\Award\CommitteeAssignment;
use App\Models\Award\SecretaryDecision;
use App\Models\Identity\User;
use App\Services\Assessments\ScoringTreeBuilder;
use App\Services\Assessments\SubmissionContentReader;
use App\Services\Award\CommitteeNotesReader;
use App\Services\Award\CommitteeScoreReader;
use App\Services\Award\CommitteeVisibilityReader;
use App\Services\Criteria\CriteriaTreeSerializer;

final readonly class ShowCommitteeEvaluation
{
    public function __construct(
        private ScoringTreeBuilder $trees,
        private CriteriaTreeSerializer $serializer,
        private CommitteeScoreReader $scores,
        private CommitteeNotesReader $notes,
        private SubmissionContentReader $content,
        private CommitteeVisibilityReader $visibility,
    ) {}

    public function handle(User $viewer, CommitteeAssignment $assignment): CommitteeEvaluationView
    {
        $assignment->loadMissing(['assessment.entity.region', 'assessment.entity.city', 'assessment.entity.owner', 'assessment.region', 'assessment.criteriaVersion', 'awardCycle', 'member', 'replaces']);
        $assessment = $assignment->assessment;
        $version = $assessment->criteriaVersion;
        $tree = $this->trees->build($version);

        return new CommitteeEvaluationView(
            new CommitteeScores($assignment, $tree, $this->scores->read($assignment, $tree, $assessment->scale_kind)),
            $this->modeFor($viewer, $assignment),
            $this->visibility->read(),
            $this->serializer->serialize($version),
            $this->content->forReviewer($assessment),
            $this->notes->read($assignment),
            $assignment->returned_at === null ? null : $this->latestReturn($assignment),
            $this->priorOf($assignment),
        );
    }

    private function modeFor(User $viewer, CommitteeAssignment $assignment): ReviewMode
    {
        $status = $assignment->assessment->status;
        $works = $assignment->member->status->canWork()
            && $assignment->status->acceptsWork()
            && $status instanceof AdvancedAssessmentStatus && $status->isInCommittee()
            && $assignment->awardCycle->status->isCommitteeStage();

        return $works && ($viewer->isSuperAdmin() || $assignment->member->isHeldBy($viewer->id)) ? ReviewMode::Edit : ReviewMode::ReadOnly;
    }

    private function latestReturn(CommitteeAssignment $assignment): ?SecretaryDecision
    {
        return SecretaryDecision::query()
            ->where('assessment_id', $assignment->assessment_id)
            ->where('kind', SecretaryDecisionKind::ReturnToCommittee)
            ->latest('id')
            ->first();
    }

    private function priorOf(CommitteeAssignment $assignment): ?PriorEvaluation
    {
        $replaced = $assignment->replaces;

        if (! $replaced instanceof CommitteeAssignment) {
            return null;
        }

        $tree = $this->trees->build($assignment->assessment->criteriaVersion);
        $replaced->setRelation('assessment', $assignment->assessment);

        return new PriorEvaluation(
            new CommitteeScores($replaced, $tree, $this->scores->read($replaced, $tree, $assignment->assessment->scale_kind)),
            $this->notes->read($replaced),
        );
    }
}
