<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Assessments\SelfAssessmentStatus;
use App\Enums\Audit\AuditEvent;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Events\Assessments\SelfAssessmentStarted;
use App\Models\Assessments\Assessment;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use App\Services\Assessments\AssessmentStateMachine;
use App\Services\Assessments\PaidRequestAlerts;
use App\Services\Assessments\RequestNumberAllocator;
use App\Services\Audit\AuditRecorder;
use App\Support\ScalarValue;
use Illuminate\Support\Facades\DB;

final readonly class OpenPaidSelfDraft
{
    public function __construct(
        private RequestNumberAllocator $numbers,
        private AssessmentStateMachine $stateMachine,
        private PaidRequestAlerts $alerts,
        private AuditRecorder $audit,
    ) {}

    public function handle(int $paymentId): ?Assessment
    {
        return DB::transaction(function () use ($paymentId): ?Assessment {
            $payment = Payment::query()->with('entity')->whereKey($paymentId)->lockForUpdate()->firstOrFail();

            if ($payment->purpose !== PaymentPurpose::SelfAssessment || $payment->status !== PaymentStatus::Paid) {
                return null;
            }

            return Assessment::query()->where('payment_id', $payment->id)->first() ?? $this->openFor($payment);
        });
    }

    private function openFor(Payment $payment): ?Assessment
    {
        Entity::withTrashed()->whereKey($payment->entity_id)->lockForUpdate()->first();
        $draftId = Assessment::query()->ownedBy($payment->entity_id)->ofPath(AssessmentPath::SelfAssessment)->open()->value('id');

        if ($draftId !== null) {
            $this->alerts->duplicate($payment, ScalarValue::int($draftId));

            return null;
        }

        $version = CriteriaVersion::query()->published()->first();

        if (! $version instanceof CriteriaVersion || $version->scale_kind === null) {
            $this->alerts->openingFailed($payment);

            return null;
        }

        $assessment = $this->create($payment, $version);
        $this->stateMachine->recordStart($assessment, null);
        $this->stateMachine->transition($assessment, SelfAssessmentStatus::Draft, AssessmentVerb::OpenDraft, null);
        $this->recordOpened($assessment, $payment);

        SelfAssessmentStarted::dispatch($assessment);

        return $assessment;
    }

    private function create(Payment $payment, CriteriaVersion $version): Assessment
    {
        $path = AssessmentPath::SelfAssessment;
        $assessment = Assessment::query()->create([
            'request_number' => $this->numbers->next($path),
            'entity_id' => $payment->entity_id,
            'path' => $path,
            'status' => SelfAssessmentStatus::AwaitingPayment,
            'criteria_version_id' => $version->id,
            'scale_kind' => $version->scale_kind,
            'payment_id' => $payment->id,
        ]);

        return $assessment->setRelation('entity', $payment->entity)->setRelation('currentResult', null)->setRelation('payment', $payment);
    }

    private function recordOpened(Assessment $assessment, Payment $payment): void
    {
        $this->audit->record(new AuditEntry(AuditEvent::AssessmentCreated, $assessment, [], [
            'request_number' => $assessment->request_number,
            'path' => AssessmentPath::SelfAssessment->value,
            'status' => SelfAssessmentStatus::AwaitingPayment->value,
            'payment_reference' => $payment->reference,
            'criteria_version_id' => $assessment->criteria_version_id,
            'scale_kind' => $assessment->scale_kind->value,
        ]));
        $this->audit->record(new AuditEntry(
            AuditEvent::AssessmentOpened,
            $assessment,
            ['status' => SelfAssessmentStatus::AwaitingPayment->value],
            ['status' => SelfAssessmentStatus::Draft->value, 'payment_reference' => $payment->reference],
        ));
    }
}
