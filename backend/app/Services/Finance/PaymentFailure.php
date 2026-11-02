<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Finance\PaymentFailureReason;
use App\Enums\Finance\PaymentStatus;
use App\Events\Finance\PaymentFailed;
use App\Models\Finance\Payment;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\Date;

final readonly class PaymentFailure
{
    public const string VERB = 'fail';

    public function __construct(private PaymentStateMachine $stateMachine, private AuditRecorder $audit) {}

    public function markFailed(Payment $locked, PaymentFailureReason $reason, ?string $code = null, ?string $message = null): void
    {
        $this->stateMachine->assertAllows($locked, PaymentStatus::Failed, self::VERB);
        $previous = $locked->status;

        $locked->forceFill([
            'failure_reason' => $reason,
            'failure_code' => $code === null ? null : mb_substr($code, 0, 60),
            'failure_message' => $message === null ? null : mb_substr($message, 0, 255),
            'verified_at' => Date::now(),
        ]);
        $this->stateMachine->transition($locked, PaymentStatus::Failed, self::VERB);

        $this->audit->record(new AuditEntry(AuditEvent::PaymentFailed, $locked, ['status' => $previous->value], [
            'status' => PaymentStatus::Failed->value,
            'failure_reason' => $reason->value,
            'failure_code' => $locked->failure_code,
        ]));

        PaymentFailed::dispatch($locked);
    }
}
