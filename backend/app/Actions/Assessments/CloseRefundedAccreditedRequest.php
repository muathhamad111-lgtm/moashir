<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Assessments\AssignmentEndReason;
use App\Enums\Audit\AuditEvent;
use App\Events\Assessments\AccreditedRequestRefunded;
use App\Models\Assessments\Assessment;
use App\Models\Finance\Refund;
use App\Services\Assessments\AssessmentRowLock;
use App\Services\Assessments\AssessmentStateMachine;
use App\Services\Assessments\AssignmentRecorder;
use App\Services\Assessments\CompletionRequestCloser;
use App\Services\Audit\AuditRecorder;
use App\Support\RequestContext;
use App\Support\ScalarValue;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class CloseRefundedAccreditedRequest
{
    public function __construct(
        private AssessmentRowLock $rowLock,
        private AssessmentStateMachine $stateMachine,
        private AssignmentRecorder $assignments,
        private CompletionRequestCloser $completionRequests,
        private RequestContext $context,
        private AuditRecorder $audit,
    ) {}

    public function handle(int $paymentId): ?Assessment
    {
        $assessmentId = Assessment::query()->where('payment_id', $paymentId)->value('id');

        if ($assessmentId === null) {
            return null;
        }

        return DB::transaction(function () use ($assessmentId, $paymentId): Assessment {
            $locked = $this->rowLock->lock(ScalarValue::int($assessmentId));

            if ($locked->status === AccreditedAssessmentStatus::Completed) {
                $this->audit->record(new AuditEntry(AuditEvent::AssessmentRefundAfterCompletion, $locked, [], ['status' => $locked->status->value]));

                return $locked;
            }

            return $locked->isOpen() ? $this->close($locked, $paymentId) : $locked;
        });
    }

    private function close(Assessment $locked, int $paymentId): Assessment
    {
        $previous = $locked->status;
        $reason = Refund::query()->where('payment_id', $paymentId)->value('reason');
        $this->context->recordReason(is_string($reason) ? $reason : null);
        $ended = $this->assignments->endActive($locked, AssignmentEndReason::Refunded);
        $this->completionRequests->cancelOpen($locked);

        $locked->refunded_at = Date::now();
        $this->stateMachine->transition($locked, AccreditedAssessmentStatus::Refunded, AssessmentVerb::Refund, null);
        $this->audit->record(new AuditEntry(AuditEvent::AssessmentRefunded, $locked, ['status' => $previous->value], ['status' => AccreditedAssessmentStatus::Refunded->value]));

        AccreditedRequestRefunded::dispatch($locked, $ended?->assessor_user_id);

        return $locked;
    }
}
