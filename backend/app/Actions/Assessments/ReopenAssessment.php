<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Assessments\AssignmentStatus;
use App\Enums\Audit\AuditEvent;
use App\Events\Assessments\RequestAssigned;
use App\Exceptions\IllegalStateTransition;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentAssignment;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentStateMachine;
use App\Services\Assessments\AssessorEligibility;
use App\Services\Assessments\AssignmentRecorder;
use App\Services\Assessments\OverrideGate;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class ReopenAssessment
{
    public function __construct(
        private OverrideGate $gate,
        private AssessorEligibility $eligibility,
        private AssignmentRecorder $assignments,
        private AssessmentStateMachine $stateMachine,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, Assessment $assessment, string $reason): Assessment
    {
        return DB::transaction(function () use ($actor, $assessment, $reason): Assessment {
            $locked = $this->gate->enter($actor, $assessment, $reason);

            if ($locked->status !== AccreditedAssessmentStatus::Completed) {
                throw IllegalStateTransition::from((string) $locked->status->value, AssessmentVerb::Reopen->value);
            }

            $assessor = $this->previousAssessorOf($actor, $locked);
            $target = $assessor instanceof User ? AccreditedAssessmentStatus::UnderReview : AccreditedAssessmentStatus::Submitted;

            if ($assessor instanceof User) {
                $this->assignments->assign($locked, $assessor, $actor, $reason);
            }

            $locked->completed_at = null;
            $this->stateMachine->override($locked, $target, AssessmentVerb::Reopen, $actor->id);
            $this->audit->record(new AuditEntry(AuditEvent::AssessmentReopened, $locked, ['status' => AccreditedAssessmentStatus::Completed->value], [
                'status' => $target->value,
                'assessor_user_id' => $assessor?->id,
            ]));

            if ($assessor instanceof User) {
                RequestAssigned::dispatch($locked, $assessor->id, null, $actor->id);
            }

            return $locked;
        });
    }

    private function previousAssessorOf(User $actor, Assessment $locked): ?User
    {
        $last = AssessmentAssignment::query()->where('assessment_id', $locked->id)->where('status', AssignmentStatus::Completed)->latest('id')->first();

        if (! $last instanceof AssessmentAssignment) {
            return null;
        }

        return $this->eligibility->find($actor, $last->assessor_user_id);
    }
}
