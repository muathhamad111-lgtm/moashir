<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Assessments\AssignmentEndReason;
use App\Events\Assessments\CompletionDeadlineExpired;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentCompletionRequest;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class CompletionRequestExpirer
{
    public function __construct(
        private AssessmentRowLock $rowLock,
        private CompletionRequestCloser $completionRequests,
        private AssignmentRecorder $assignments,
        private AssessmentStateMachine $stateMachine,
        private InitialReviewSla $sla,
        private AuditRecorder $audit,
        private AdvancedResumeTarget $resumeTarget,
    ) {}

    public function expire(int $assessmentId): bool
    {
        return DB::transaction(function () use ($assessmentId): bool {
            $locked = $this->rowLock->lock($assessmentId);
            $request = $this->expiredRequestOf($locked);

            if (! $request instanceof AssessmentCompletionRequest) {
                return false;
            }

            $this->applyOutcome($locked, $request);

            return true;
        });
    }

    private function expiredRequestOf(Assessment $locked): ?AssessmentCompletionRequest
    {
        if (! $locked->path->isReviewed() || $locked->status !== $locked->path->reviewStages()->needsCompletion || $locked->isFrozen() || $locked->awaitsCriteriaUpdate()) {
            return null;
        }

        $request = $locked->loadMissing('openCompletionRequest')->openCompletionRequest;

        return $request instanceof AssessmentCompletionRequest && $request->hasExpired(Date::now()) ? $request : null;
    }

    private function applyOutcome(Assessment $locked, AssessmentCompletionRequest $request): void
    {
        $outcome = $request->expiry_outcome;
        $resumesTo = $outcome->endsAssignment() ? null : $this->resumePointOf($locked, $request);
        $assessorId = $locked->loadMissing('activeAssignment')->activeAssignment?->assessor_user_id;
        $next = $outcome->assessmentStatusOn($locked->path, $resumesTo);
        $previous = $locked->status;

        $this->completionRequests->expire($locked, $outcome);

        if ($outcome->endsAssignment()) {
            $this->assignments->endActive($locked, AssignmentEndReason::DeclinedIncomplete);
        }

        if ($next->runsReviewClock()) {
            $this->sla->resumeAfter($locked, $request);
        }

        $this->stateMachine->transition($locked, $next, AssessmentVerb::ExpireCompletion, null);
        $this->audit->record(new AuditEntry($outcome->auditEvent(), $locked, ['status' => $previous->value], [
            'status' => $next->value,
            'completion_request_id' => $request->id,
            'expiry_outcome' => $outcome->value,
            'deadline_at' => $request->deadline_at->toIso8601String(),
        ]));

        CompletionDeadlineExpired::dispatch($locked, $request->refresh(), $assessorId);
    }

    /**
     * A return made after the assignment continues with its assessor only while that assessor can still work on it.
     */
    private function resumePointOf(Assessment $locked, AssessmentCompletionRequest $request): ?AdvancedAssessmentStatus
    {
        return $request->resumes_to instanceof AdvancedAssessmentStatus ? $this->resumeTarget->resolve($locked, $request->resumes_to) : null;
    }
}
