<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Contracts\Assessments\ReviewedAssessmentState;
use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Audit\AuditEvent;
use App\Events\Assessments\RequestAssigned;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Exceptions\IllegalStateTransition;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentAssignment;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentStateMachine;
use App\Services\Assessments\AssessorEligibility;
use App\Services\Assessments\AssignmentRecorder;
use App\Services\Assessments\ReviewWriteGate;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class ReassignAssessor
{
    public function __construct(
        private ReviewWriteGate $gate,
        private AssessorEligibility $eligibility,
        private AssignmentRecorder $assignments,
        private AssessmentStateMachine $stateMachine,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, Assessment $assessment, int $assessorId, string $reason): Assessment
    {
        return DB::transaction(function () use ($actor, $assessment, $assessorId, $reason): Assessment {
            $locked = $this->gate->enter($actor, $assessment, $assessment->status, AssessmentVerb::Reassign, $reason);
            $current = $this->currentAssignmentOf($locked);

            if ($current->assessor_user_id === $assessorId) {
                throw AssessmentRejected::assessorUnchanged();
            }

            $assessor = $this->eligibility->assertEligible($actor, $assessorId, $locked->path, $locked->award_cycle_id);
            $assignment = $this->assignments->handOver($locked, $current, $assessor, $actor, $reason);
            $this->stateMachine->transition($locked, $locked->status, AssessmentVerb::Reassign, $actor->id);
            $this->audit->record(new AuditEntry(AuditEvent::AssessmentReassigned, $locked, ['assessor_user_id' => $current->assessor_user_id], [
                'assessor_user_id' => $assessor->id,
                'assignment_id' => $assignment->id,
                'status' => $locked->status->value,
            ]));

            RequestAssigned::dispatch($locked, $assessor->id, $current->assessor_user_id, $actor->id);

            return $locked;
        });
    }

    private function currentAssignmentOf(Assessment $locked): AssessmentAssignment
    {
        if (! $locked->status instanceof ReviewedAssessmentState || ! $locked->status->hasAssessor()) {
            throw IllegalStateTransition::from((string) $locked->status->value, AssessmentVerb::Reassign->value);
        }

        return $locked->loadMissing('activeAssignment')->activeAssignment ?? throw AssessmentRejected::assignmentRequired();
    }
}
