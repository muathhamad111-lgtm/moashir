<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Contracts\Demo\DemoAccreditedRequestSource;
use App\Data\Audit\AuditEntry;
use App\Data\Demo\DemoAccreditedResult;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Audit\AuditEvent;
use App\Exceptions\Console\DemoDataRefused;
use App\Models\Assessments\Assessment;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentStateMachine;
use App\Services\Assessments\RequestNumberAllocator;
use App\Services\Audit\AuditRecorder;

final readonly class DemoRecordedRequestSource implements DemoAccreditedRequestSource
{
    private const AccreditedAssessmentStatus CLOSED_UNTIL_COMPLETED = AccreditedAssessmentStatus::DeclinedIncomplete;

    public function __construct(
        private DemoPaymentInstaller $payments,
        private RequestNumberAllocator $numbers,
        private DemoRequestCompletion $completion,
        private AssessmentStateMachine $stateMachine,
        private AuditRecorder $audit,
    ) {}

    public function completedRequestFor(DemoAccreditedResult $plan, User $assessor): Assessment
    {
        $payment = $this->payments->writeOrder($plan->owner);
        $request = $this->insertClosed($payment, $plan);
        $this->payments->settle($payment);
        $this->completion->complete($request, $plan, $assessor);
        $this->stateMachine->recordStart($request, null);
        $this->recordCreation($request, $payment);

        return $request;
    }

    private function insertClosed(Payment $payment, DemoAccreditedResult $plan): Assessment
    {
        $version = $this->publishedVersion();
        $request = new Assessment;
        $request->forceFill([
            'request_number' => $this->numbers->next(AssessmentPath::Accredited),
            'entity_id' => $payment->entity_id,
            'path' => AssessmentPath::Accredited,
            'status' => self::CLOSED_UNTIL_COMPLETED,
            'criteria_version_id' => $version->id,
            'scale_kind' => $version->scale_kind,
            'payment_id' => $payment->id,
            'submitted_at' => $plan->submittedAt(),
        ])->save();

        return $request;
    }

    private function publishedVersion(): CriteriaVersion
    {
        $version = CriteriaVersion::query()->published()->first();

        if (! $version instanceof CriteriaVersion || $version->scale_kind === null) {
            throw DemoDataRefused::missingPrerequisite('no criteria version is published (moashir:criteria:install-seed)');
        }

        return $version;
    }

    private function recordCreation(Assessment $request, Payment $payment): void
    {
        $this->audit->record(new AuditEntry(AuditEvent::AssessmentCreated, $request, [], [
            'request_number' => $request->request_number,
            'path' => AssessmentPath::Accredited->value,
            'status' => AccreditedAssessmentStatus::Completed->value,
            'payment_reference' => $payment->reference,
            'criteria_version_id' => $request->criteria_version_id,
            'scale_kind' => $request->scale_kind->value,
        ]));
    }
}
