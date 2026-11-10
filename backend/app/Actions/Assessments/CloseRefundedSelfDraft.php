<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Assessments\SelfAssessmentStatus;
use App\Enums\Audit\AuditEvent;
use App\Models\Assessments\Assessment;
use App\Services\Assessments\AssessmentRowLock;
use App\Services\Assessments\AssessmentStateMachine;
use App\Services\Audit\AuditRecorder;
use App\Support\ScalarValue;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class CloseRefundedSelfDraft
{
    public function __construct(
        private AssessmentRowLock $rowLock,
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

            if ($locked->status !== SelfAssessmentStatus::Draft) {
                $this->audit->record(new AuditEntry(AuditEvent::AssessmentRefundAfterCompletion, $locked, [], ['status' => (string) $locked->status->value]));

                return $locked;
            }

            $locked->refunded_at = Date::now();
            $this->stateMachine->transition($locked, SelfAssessmentStatus::Refunded, AssessmentVerb::Refund, null);
            $this->audit->record(new AuditEntry(AuditEvent::AssessmentRefunded, $locked, ['status' => SelfAssessmentStatus::Draft->value], ['status' => SelfAssessmentStatus::Refunded->value]));

            return $locked;
        });
    }
}
