<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Audit\AuditEvent;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Events\Assessments\AccreditedRequestOpened;
use App\Models\Assessments\Assessment;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use App\Services\Assessments\AccreditedRequestAlerts;
use App\Services\Assessments\AssessmentStateMachine;
use App\Services\Assessments\RequestNumberAllocator;
use App\Services\Audit\AuditRecorder;
use App\Services\Finance\PaymentAlerts;
use App\Support\ScalarValue;
use Illuminate\Support\Facades\DB;

final readonly class OpenPaidAccreditedRequest
{
    public function __construct(
        private RequestNumberAllocator $numbers,
        private AssessmentStateMachine $stateMachine,
        private PaymentAlerts $alerts,
        private AccreditedRequestAlerts $requestAlerts,
        private AuditRecorder $audit,
    ) {}

    public function handle(int $paymentId): ?Assessment
    {
        return DB::transaction(function () use ($paymentId): ?Assessment {
            $payment = Payment::query()->with('entity')->whereKey($paymentId)->lockForUpdate()->firstOrFail();

            if ($payment->purpose !== PaymentPurpose::Accredited || $payment->status !== PaymentStatus::Paid) {
                return null;
            }

            $opened = Assessment::query()->where('payment_id', $payment->id)->first();

            return $opened instanceof Assessment ? $opened : $this->openFor($payment);
        });
    }

    private function openFor(Payment $payment): ?Assessment
    {
        Entity::withTrashed()->whereKey($payment->entity_id)->lockForUpdate()->first();
        $openId = Assessment::query()->ownedBy($payment->entity_id)->ofPath(AssessmentPath::Accredited)->open()->value('id');

        if ($openId !== null) {
            $this->recordDuplicate($payment, ScalarValue::int($openId));

            return null;
        }

        $version = CriteriaVersion::query()->published()->first();

        if (! $version instanceof CriteriaVersion || $version->scale_kind === null) {
            $this->requestAlerts->openingFailed($payment);

            return null;
        }

        $assessment = $this->create($payment, $version);

        $this->stateMachine->recordStart($assessment, null);
        $this->stateMachine->transition($assessment, AccreditedAssessmentStatus::Draft, AssessmentVerb::OpenDraft, null);
        $this->recordOpened($assessment, $payment);

        AccreditedRequestOpened::dispatch($assessment);

        return $assessment;
    }

    private function create(Payment $payment, CriteriaVersion $version): Assessment
    {
        $path = AssessmentPath::Accredited;
        $assessment = Assessment::query()->create([
            'request_number' => $this->numbers->next($path),
            'entity_id' => $payment->entity_id,
            'path' => $path,
            'status' => $path->initialState(),
            'criteria_version_id' => $version->id,
            'scale_kind' => $version->scale_kind,
            'payment_id' => $payment->id,
        ]);

        return $assessment->setRelation('entity', $payment->entity)->setRelation('currentResult', null);
    }

    private function recordOpened(Assessment $assessment, Payment $payment): void
    {
        $this->audit->record(new AuditEntry(AuditEvent::AssessmentCreated, $assessment, [], [
            'request_number' => $assessment->request_number,
            'path' => AssessmentPath::Accredited->value,
            'status' => AccreditedAssessmentStatus::AwaitingPayment->value,
            'payment_reference' => $payment->reference,
            'criteria_version_id' => $assessment->criteria_version_id,
            'scale_kind' => $assessment->scale_kind->value,
        ]));
        $this->audit->record(new AuditEntry(
            AuditEvent::AssessmentOpened,
            $assessment,
            ['status' => AccreditedAssessmentStatus::AwaitingPayment->value],
            ['status' => AccreditedAssessmentStatus::Draft->value, 'payment_reference' => $payment->reference],
        ));
    }

    private function recordDuplicate(Payment $payment, int $openId): void
    {
        $this->audit->record(new AuditEntry(AuditEvent::AssessmentDuplicatePayment, $payment, [], [
            'payment_reference' => $payment->reference,
            'open_assessment_id' => $openId,
        ]));
        $this->alerts->refundRequired($payment);
    }
}
