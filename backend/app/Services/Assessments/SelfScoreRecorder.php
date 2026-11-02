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

final readonly class SelfScoreRecorder
{
    public function __construct(
        private ScoreInputParser $parser,
        private ScoreSheetWriter $writer,
        private AuditRecorder $audit,
    ) {}

    public function record(User $scorer, Assessment $assessment, ScoringTree $tree, SubmittedScores $scores): RecordedScores
    {
        $sheet = $this->parser->parse($scores, $tree, $assessment->scale_kind);
        $changes = $this->writer->replace($assessment, $tree, $sheet, $scorer, ScorerRole::Entity);

        if (! $changes->isEmpty()) {
            $assessment->touch();
            $this->audit->record(new AuditEntry(AuditEvent::AssessmentScoresSaved, $assessment, $changes->before(), $changes->after()));
        }

        return new RecordedScores($sheet, $changes);
    }
}
