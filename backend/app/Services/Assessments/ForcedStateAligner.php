<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssignmentEndReason;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentAssignment;
use Illuminate\Support\Facades\Date;

final readonly class ForcedStateAligner
{
    public function __construct(
        private AssignmentRecorder $assignments,
        private CompletionRequestCloser $completionRequests,
        private AccreditedReviewSla $sla,
    ) {}

    public function alignTo(Assessment $locked, AccreditedAssessmentStatus $target): void
    {
        $this->completionRequests->cancelOpen($locked);

        match ($target) {
            AccreditedAssessmentStatus::Draft => $this->toDraft($locked),
            AccreditedAssessmentStatus::Submitted => $this->toQueue($locked),
            AccreditedAssessmentStatus::Assigned, AccreditedAssessmentStatus::UnderReview => $this->toReview($locked),
            default => $this->toCompleted($locked),
        };
    }

    private function toDraft(Assessment $locked): void
    {
        $this->assignments->endActive($locked, AssignmentEndReason::ForcedTransition);
        $locked->forceFill(['submitted_at' => null, 'due_at' => null, 'review_sla_days' => null, 'overdue_notified_at' => null, 'completed_at' => null]);
    }

    private function toQueue(Assessment $locked): void
    {
        $this->assignments->endActive($locked, AssignmentEndReason::ForcedTransition);
        $this->ensureSubmitted($locked);
    }

    private function toReview(Assessment $locked): void
    {
        if (! $locked->loadMissing('activeAssignment')->activeAssignment instanceof AssessmentAssignment) {
            throw AssessmentRejected::assignmentRequired();
        }

        $this->ensureSubmitted($locked);
    }

    private function toCompleted(Assessment $locked): void
    {
        if (! $locked->hasResult()) {
            throw AssessmentRejected::resultRequired();
        }

        $active = $locked->loadMissing('activeAssignment')->activeAssignment;

        if ($active instanceof AssessmentAssignment) {
            $this->assignments->complete($locked, $active);
        }

        $locked->completed_at ??= Date::now();
    }

    private function ensureSubmitted(Assessment $locked): void
    {
        $locked->completed_at = null;

        if ($locked->submitted_at !== null) {
            return;
        }

        $now = Date::now();
        $locked->forceFill(['submitted_at' => $now, 'due_at' => $this->sla->dueAt($now), 'review_sla_days' => $this->sla->days(), 'overdue_notified_at' => null]);
    }
}
