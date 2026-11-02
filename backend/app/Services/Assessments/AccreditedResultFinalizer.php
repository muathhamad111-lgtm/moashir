<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\ScoredAssessment;
use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Assessments\ResultSnapshotKind;
use App\Enums\Audit\AuditEvent;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentAssignment;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\Date;

final readonly class AccreditedResultFinalizer
{
    public function __construct(
        private ScoringEngine $engine,
        private ResultSnapshotRecorder $snapshots,
        private AssignmentRecorder $assignments,
        private AssessmentStateMachine $stateMachine,
        private AuditRecorder $audit,
    ) {}

    public function finalize(User $actor, Assessment $locked, AssessmentAssignment $assignment, ScoredAssessment $scored): AssessmentResultSnapshot
    {
        $snapshot = $this->snapshots->record($locked, $this->engine->compute($scored->tree, $scored->sheet), ResultSnapshotKind::AccreditedResult, $actor->id);
        $locked->completed_at = Date::now();
        $this->stateMachine->transition($locked, AccreditedAssessmentStatus::Completed, AssessmentVerb::Complete, $actor->id);
        $this->assignments->complete($locked, $assignment);

        $this->audit->record(new AuditEntry(AuditEvent::AssessmentCompleted, $locked, ['status' => AccreditedAssessmentStatus::UnderReview->value], [
            'status' => AccreditedAssessmentStatus::Completed->value,
            'result_snapshot_id' => $snapshot->id,
            'total' => $snapshot->total_score->toDecimal(),
            'percent' => $snapshot->percent->toDecimal(),
        ]));

        return $snapshot;
    }
}
