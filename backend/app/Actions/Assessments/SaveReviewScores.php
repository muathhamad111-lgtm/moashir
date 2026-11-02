<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\AssessmentScores;
use App\Data\Assessments\SubmittedScores;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Audit\AuditEvent;
use App\Exceptions\IllegalStateTransition;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Assessments\AssigneeGuard;
use App\Services\Assessments\ReviewWriteGate;
use App\Services\Assessments\ScoreChangesRecorder;
use App\Services\Assessments\ScoringTreeBuilder;
use Illuminate\Support\Facades\DB;

final readonly class SaveReviewScores
{
    public function __construct(
        private ReviewWriteGate $gate,
        private AssigneeGuard $assignee,
        private ScoringTreeBuilder $trees,
        private ScoreChangesRecorder $scores,
    ) {}

    public function handle(User $actor, Assessment $assessment, SubmittedScores $changes, ?string $reason): AssessmentScores
    {
        return DB::transaction(function () use ($actor, $assessment, $changes, $reason): AssessmentScores {
            $locked = $this->gate->enter($actor, $assessment, $assessment->path->reviewStages()->underReview, AssessmentVerb::ScoreReview, $reason);
            $this->assignee->assertActsOn($actor, $locked);
            $this->assertUnderReview($locked);

            $tree = $this->trees->build($locked->criteriaVersion);
            $recorded = $this->scores->record($actor, $locked, $tree, $changes, AuditEvent::AssessmentScoresSaved);

            return new AssessmentScores($locked, $tree, $recorded->sheet);
        });
    }

    private function assertUnderReview(Assessment $locked): void
    {
        if ($locked->status !== $locked->path->reviewStages()->underReview) {
            throw IllegalStateTransition::from((string) $locked->status->value, AssessmentVerb::ScoreReview->value);
        }
    }
}
