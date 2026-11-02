<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\CommitteeAssignmentStatus;
use App\Events\Award\CommitteeEvaluationStarted;
use App\Models\Award\CommitteeAssignment;
use App\Services\Assessments\AssessmentStateMachine;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\Date;

final readonly class CommitteeWorkStarter
{
    public function __construct(private AssessmentStateMachine $stateMachine, private AuditRecorder $audit) {}

    public function begin(CommitteeAssignment $locked, int $actorId): void
    {
        $assessment = $locked->assessment;

        if ($assessment->status === AdvancedAssessmentStatus::ReturnedToCommittee) {
            $this->stateMachine->transition($assessment, AdvancedAssessmentStatus::UnderCommitteeAssessment, AssessmentVerb::ResumeCommittee, $actorId);
        }

        if ($locked->status !== CommitteeAssignmentStatus::NotStarted) {
            return;
        }

        $locked->forceFill(['status' => CommitteeAssignmentStatus::InProgress, 'started_at' => Date::now()])->save();
        $this->audit->record(new AuditEntry(AuditEvent::CommitteeStarted, $locked, ['status' => CommitteeAssignmentStatus::NotStarted->value], [
            'status' => CommitteeAssignmentStatus::InProgress->value,
            'assessment_id' => $locked->assessment_id,
        ]));

        CommitteeEvaluationStarted::dispatch($locked);
    }
}
