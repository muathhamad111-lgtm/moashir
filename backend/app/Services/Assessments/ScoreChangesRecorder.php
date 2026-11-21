<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\RecordedScores;
use App\Data\Assessments\ScoringTree;
use App\Data\Assessments\SubmittedScores;
use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\ScorerRole;
use App\Enums\Audit\AuditEvent;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;

final readonly class ScoreChangesRecorder
{
    public function __construct(
        private DesiredScoreSheet $desiredSheets,
        private ScoreSheetWriter $writer,
        private AuditRecorder $audit,
    ) {}

    public function record(User $scorer, Assessment $assessment, ScoringTree $tree, SubmittedScores $changes, AuditEvent $event): RecordedScores
    {
        $desired = $this->desiredSheets->build($assessment, $tree, $changes);
        $recorded = $this->writer->replace($assessment, $tree, $desired, $scorer, $this->roleFor($assessment));

        if (! $recorded->isEmpty()) {
            $assessment->touch();
            $this->audit->record(new AuditEntry($event, $assessment, $recorded->before(), $recorded->after()));
        }

        return new RecordedScores($desired->sheet, $recorded);
    }

    private function roleFor(Assessment $assessment): ScorerRole
    {
        return $assessment->path->isReviewed() ? ScorerRole::Assessor : ScorerRole::Entity;
    }
}
