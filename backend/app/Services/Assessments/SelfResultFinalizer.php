<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\ScoreSheet;
use App\Data\Assessments\ScoringTree;
use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Assessments\ResultSnapshotKind;
use App\Enums\Assessments\SelfAssessmentStatus;
use App\Enums\Audit\AuditEvent;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\Date;

final readonly class SelfResultFinalizer
{
    public function __construct(
        private AssessmentStateMachine $stateMachine,
        private ScoringEngine $engine,
        private ResultSnapshotRecorder $snapshots,
        private AuditRecorder $audit,
    ) {}

    public function finalize(User $actor, Assessment $assessment, ScoringTree $tree, ScoreSheet $sheet): AssessmentResultSnapshot
    {
        $this->submit($actor, $assessment, $sheet);

        return $this->computeResult($assessment, $tree, $sheet);
    }

    private function submit(User $actor, Assessment $assessment, ScoreSheet $sheet): void
    {
        $assessment->submitted_at = Date::now();
        $this->stateMachine->transition($assessment, SelfAssessmentStatus::Submitted, AssessmentVerb::Submit, $actor->id);
        $this->audit->record(new AuditEntry(
            AuditEvent::AssessmentSubmitted,
            $assessment,
            ['status' => SelfAssessmentStatus::Draft->value],
            ['status' => SelfAssessmentStatus::Submitted->value, 'scored_items' => $sheet->count()],
        ));
    }

    private function computeResult(Assessment $assessment, ScoringTree $tree, ScoreSheet $sheet): AssessmentResultSnapshot
    {
        $snapshot = $this->snapshots->record($assessment, $this->engine->compute($tree, $sheet), ResultSnapshotKind::SelfResult, null);
        $assessment->completed_at = Date::now();
        $this->stateMachine->transition($assessment, SelfAssessmentStatus::ResultAvailable, AssessmentVerb::ComputeResult, null);
        $this->audit->record(new AuditEntry(AuditEvent::AssessmentResultComputed, $assessment, ['status' => SelfAssessmentStatus::Submitted->value], [
            'status' => SelfAssessmentStatus::ResultAvailable->value,
            'result_snapshot_id' => $snapshot->id,
            'total' => $snapshot->total_score->toDecimal(),
            'percent' => $snapshot->percent->toDecimal(),
        ]));

        return $snapshot;
    }
}
