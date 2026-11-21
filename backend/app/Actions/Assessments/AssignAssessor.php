<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Audit\AuditEvent;
use App\Events\Assessments\RequestAssigned;
use App\Exceptions\IllegalStateTransition;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentStateMachine;
use App\Services\Assessments\AssessorEligibility;
use App\Services\Assessments\AssignmentRecorder;
use App\Services\Assessments\InitialReviewSla;
use App\Services\Assessments\ReviewWriteGate;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class AssignAssessor
{
    public function __construct(
        private ReviewWriteGate $gate,
        private AssessorEligibility $eligibility,
        private AssignmentRecorder $assignments,
        private InitialReviewSla $initialReviewSla,
        private AssessmentStateMachine $stateMachine,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, Assessment $assessment, int $assessorId, ?string $reason): Assessment
    {
        return DB::transaction(function () use ($actor, $assessment, $assessorId, $reason): Assessment {
            $stages = $assessment->path->reviewStages();
            $locked = $this->gate->enter($actor, $assessment, $stages->assigned, AssessmentVerb::Assign, $reason);

            if ($locked->status !== $stages->awaitingAssignment) {
                throw IllegalStateTransition::from((string) $locked->status->value, AssessmentVerb::Assign->value);
            }

            $assessor = $this->eligibility->assertEligible($actor, $assessorId, $locked->path, $locked->award_cycle_id);

            $assignment = $this->assignments->assign($locked, $assessor, $actor, $reason);
            $this->initialReviewSla->startFor($locked);
            $this->stateMachine->transition($locked, $stages->assigned, AssessmentVerb::Assign, $actor->id);
            $this->audit->record(new AuditEntry(AuditEvent::AssessmentAssigned, $locked, ['status' => $stages->awaitingAssignment->value], [
                'status' => $stages->assigned->value,
                'assignment_id' => $assignment->id,
                'assessor_user_id' => $assessor->id,
                'due_at' => $locked->due_at?->toIso8601String(),
            ]));

            RequestAssigned::dispatch($locked, $assessor->id, null, $actor->id);

            return $locked;
        });
    }
}
