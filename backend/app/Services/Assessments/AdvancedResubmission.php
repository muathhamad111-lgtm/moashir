<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Audit\AuditEvent;
use App\Events\Assessments\AdvancedRequestResubmitted;
use App\Events\Assessments\AdvancedRequestResubmittedToAssessor;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentAssignment;
use App\Models\Assessments\AssessmentCompletionRequest;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;

final readonly class AdvancedResubmission
{
    public function __construct(
        private CompletionRequestCloser $completionRequests,
        private InitialReviewSla $sla,
        private AssessmentStateMachine $stateMachine,
        private AuditRecorder $audit,
        private AdvancedResumeTarget $resumeTarget,
    ) {}

    public function resubmit(User $actor, Assessment $locked): Assessment
    {
        $open = $locked->loadMissing('openCompletionRequest')->openCompletionRequest;
        $target = $this->resumeTarget->resolve($locked, $open instanceof AssessmentCompletionRequest ? $open->resumes_to : null);

        if ($open instanceof AssessmentCompletionRequest && $target->runsReviewClock()) {
            $this->sla->resumeAfter($locked, $open);
        }

        $this->stateMachine->transition($locked, $target, AssessmentVerb::Resubmit, $actor->id);
        $resolved = $this->completionRequests->resolve($locked);
        $this->audit->record(new AuditEntry(AuditEvent::AwardResubmitted, $locked, ['status' => AdvancedAssessmentStatus::NeedsCompletion->value], [
            'status' => $target->value,
            'completion_request_id' => $resolved?->id,
            'due_at' => $target->runsReviewClock() ? $locked->due_at?->toIso8601String() : null,
        ]));

        $this->announce($actor, $locked, $target);

        return $locked;
    }

    private function announce(User $actor, Assessment $locked, AdvancedAssessmentStatus $target): void
    {
        $assignment = $target->hasAssessor() ? $locked->loadMissing('activeAssignment')->activeAssignment : null;

        if ($assignment instanceof AssessmentAssignment) {
            AdvancedRequestResubmittedToAssessor::dispatch($locked, $assignment->assessor_user_id);

            return;
        }

        AdvancedRequestResubmitted::dispatch($locked, $actor->id);
    }
}
