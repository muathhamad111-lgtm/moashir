<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Audit\AuditEvent;
use App\Events\Assessments\AccreditedRequestResubmitted;
use App\Events\Assessments\AccreditedRequestSubmitted;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentAssignment;
use App\Models\Identity\User;
use App\Services\Assessments\AccreditedReviewSla;
use App\Services\Assessments\AssessmentStateMachine;
use App\Services\Assessments\BoundSubCriteriaReader;
use App\Services\Assessments\CompletionRequestCloser;
use App\Services\Assessments\EntityContentGate;
use App\Services\Assessments\SubmissionGapsReader;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class SubmitAccreditedRequest
{
    public function __construct(
        private EntityContentGate $gate,
        private BoundSubCriteriaReader $subs,
        private SubmissionGapsReader $gaps,
        private AccreditedReviewSla $sla,
        private AssessmentStateMachine $stateMachine,
        private CompletionRequestCloser $completionRequests,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, Assessment $assessment, ?string $reason): Assessment
    {
        return DB::transaction(function () use ($actor, $assessment, $reason): Assessment {
            $locked = $this->gate->enter($actor, $assessment, $reason);

            if ($locked->awaitsCriteriaUpdate()) {
                throw AssessmentRejected::criteriaUpdateInProgress();
            }

            $gaps = $this->gaps->read($locked, $this->subs->of($locked->criteriaVersion));

            if (! $gaps->isEmpty()) {
                throw AssessmentRejected::submissionIncomplete($gaps->fieldErrors(), $gaps->toArray());
            }

            return $locked->status === AccreditedAssessmentStatus::Draft ? $this->submit($actor, $locked) : $this->resubmit($actor, $locked);
        });
    }

    private function submit(User $actor, Assessment $locked): Assessment
    {
        $now = Date::now();
        $locked->forceFill(['submitted_at' => $now, 'due_at' => $this->sla->dueAt($now), 'review_sla_days' => $this->sla->days(), 'overdue_notified_at' => null]);
        $this->stateMachine->transition($locked, AccreditedAssessmentStatus::Submitted, AssessmentVerb::Submit, $actor->id);
        $this->audit->record(new AuditEntry(AuditEvent::AssessmentSubmitted, $locked, ['status' => AccreditedAssessmentStatus::Draft->value], [
            'status' => AccreditedAssessmentStatus::Submitted->value,
            'due_at' => $locked->due_at?->toIso8601String(),
        ]));

        AccreditedRequestSubmitted::dispatch($locked);

        return $locked;
    }

    private function resubmit(User $actor, Assessment $locked): Assessment
    {
        $this->stateMachine->transition($locked, AccreditedAssessmentStatus::UnderReview, AssessmentVerb::Resubmit, $actor->id);
        $resolved = $this->completionRequests->resolve($locked);
        $this->audit->record(new AuditEntry(AuditEvent::AssessmentResubmitted, $locked, ['status' => AccreditedAssessmentStatus::NeedsCompletion->value], [
            'status' => AccreditedAssessmentStatus::UnderReview->value,
            'completion_request_id' => $resolved?->id,
        ]));

        $assignment = $locked->loadMissing('activeAssignment')->activeAssignment;

        if ($assignment instanceof AssessmentAssignment) {
            AccreditedRequestResubmitted::dispatch($locked, $assignment->assessor_user_id);
        }

        return $locked;
    }
}
