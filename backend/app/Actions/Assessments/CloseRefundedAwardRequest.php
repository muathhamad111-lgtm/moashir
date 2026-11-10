<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Assessments\AssignmentEndReason;
use App\Enums\Audit\AuditEvent;
use App\Models\Assessments\Assessment;
use App\Services\Assessments\AssessmentRowLock;
use App\Services\Assessments\AssessmentStateMachine;
use App\Services\Assessments\AssignmentRecorder;
use App\Services\Assessments\CompletionRequestCloser;
use App\Services\Audit\AuditRecorder;
use App\Services\Award\CommitteeRequestCloser;
use App\Support\ScalarValue;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class CloseRefundedAwardRequest
{
    public function __construct(
        private AssessmentRowLock $rowLock,
        private AssignmentRecorder $assignments,
        private CompletionRequestCloser $completionRequests,
        private CommitteeRequestCloser $committee,
        private AssessmentStateMachine $stateMachine,
        private AuditRecorder $audit,
    ) {}

    public function handle(int $paymentId): ?Assessment
    {
        $assessmentId = Assessment::query()->where('payment_id', $paymentId)->value('id');

        if ($assessmentId === null) {
            return null;
        }

        return DB::transaction(function () use ($assessmentId): Assessment {
            $locked = $this->rowLock->lock(ScalarValue::int($assessmentId));
            $previous = $locked->status;

            if (! $previous instanceof AdvancedAssessmentStatus || ! $previous->isRefundable()) {
                $this->audit->record(new AuditEntry(AuditEvent::AssessmentRefundAfterCompletion, $locked, [], ['status' => (string) $previous->value]));

                return $locked;
            }

            $this->assignments->endActive($locked, AssignmentEndReason::Refunded);
            $this->completionRequests->cancelOpen($locked);
            $this->committee->closeOnWithdrawal($locked);
            $locked->refunded_at = Date::now();
            $this->stateMachine->transition($locked, AdvancedAssessmentStatus::Refunded, AssessmentVerb::Refund, null);
            $this->audit->record(new AuditEntry(AuditEvent::AssessmentRefunded, $locked, ['status' => $previous->value], ['status' => AdvancedAssessmentStatus::Refunded->value]));

            return $locked;
        });
    }
}
