<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Models\Finance\Payment;
use App\Services\Audit\AuditRecorder;
use App\Services\Finance\PaymentAlerts;

final readonly class PaidRequestAlerts
{
    public function __construct(
        private AccreditedRequestAlerts $requestAlerts,
        private PaymentAlerts $paymentAlerts,
        private AuditRecorder $audit,
    ) {}

    public function duplicate(Payment $payment, int $openAssessmentId): void
    {
        $this->audit->record(new AuditEntry(AuditEvent::AssessmentDuplicatePayment, $payment, [], [
            'payment_reference' => $payment->reference,
            'open_assessment_id' => $openAssessmentId,
        ]));
        $this->paymentAlerts->refundRequired($payment);
    }

    public function openingFailed(Payment $payment): void
    {
        $this->requestAlerts->paidRequestOpeningFailed($payment);
    }
}
