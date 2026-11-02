<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Audit\AuditEntry;
use App\Data\Finance\RefundInstruction;
use App\Enums\Audit\AuditEvent;
use App\Enums\Files\StoredFilePurpose;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Finance\RefundStatus;
use App\Events\Finance\PaymentRefunded;
use App\Events\Finance\RefundRecorded;
use App\Exceptions\Finance\RefundRejected;
use App\Models\Files\StoredFile;
use App\Models\Finance\Payment;
use App\Models\Finance\Refund;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Files\FileIntake;
use App\Services\Files\StoredFileRemover;
use App\Services\Finance\PaymentEventRecorder;
use App\Services\Finance\PaymentRowLock;
use App\Services\Finance\PaymentStateMachine;
use App\Services\Finance\ReceiptVoider;
use App\Support\RequestContext;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class RecordRefund
{
    public const string VERB = 'record_refund';

    private const string EVIDENCE_FIELD = 'evidence';

    public function __construct(
        private FileIntake $files,
        private StoredFileRemover $remover,
        private PaymentRowLock $locks,
        private PaymentStateMachine $stateMachine,
        private ReceiptVoider $receipts,
        private PaymentEventRecorder $events,
        private RequestContext $context,
        private AuditRecorder $audit,
        private Repository $config,
    ) {}

    public function handle(User $actor, Payment $payment, RefundInstruction $instruction): Refund
    {
        $this->assertRecordable($payment, $instruction);
        $this->context->recordReason($instruction->reason);
        $evidence = $this->files->accept($instruction->evidence, StoredFilePurpose::RefundEvidence, $actor, self::EVIDENCE_FIELD);

        try {
            return DB::transaction(fn (): Refund => $this->record($actor, $payment, $instruction, $evidence));
        } catch (Throwable $failure) {
            $this->remover->remove($evidence);

            throw $failure;
        }
    }

    private function assertRecordable(Payment $payment, RefundInstruction $instruction): void
    {
        $this->stateMachine->assertAllows($payment, PaymentStatus::Refunded, self::VERB);

        if (! $instruction->amount->equals($payment->totalMoney())) {
            throw RefundRejected::amountMustEqualTotal($payment->total);
        }

        $paidOn = $payment->paid_at?->copy()->setTimezone($this->config->string('app.display_timezone'))->toDateString();

        if ($paidOn !== null && $instruction->refundedOn->toDateString() < $paidOn) {
            throw RefundRejected::dateBeforePayment();
        }
    }

    private function record(User $actor, Payment $payment, RefundInstruction $instruction, StoredFile $evidence): Refund
    {
        $locked = $this->locks->lock($payment);

        if (Refund::query()->where('payment_id', $locked->id)->exists()) {
            throw RefundRejected::alreadyRecorded();
        }

        $refund = Refund::query()->create([
            'payment_id' => $locked->id,
            'amount' => $instruction->amount->amount,
            'currency' => $instruction->amount->currency,
            'refunded_on' => $instruction->refundedOn->toDateString(),
            'reason' => $instruction->reason,
            'transfer_reference' => $instruction->transferReference,
            'evidence_file_id' => $evidence->id,
            'status' => RefundStatus::Recorded,
            'recorded_by_user_id' => $actor->id,
        ]);
        $evidence->attachable()->associate($refund)->save();

        $this->stateMachine->transition($locked, PaymentStatus::Refunded, self::VERB);
        $this->receipts->voidFor($locked, $refund);
        $this->events->recordAdmin($locked, 'refund_recorded', $actor, ['refund_id' => $refund->public_id, 'amount' => $refund->amount]);
        $this->audit($locked, $refund);

        RefundRecorded::dispatch($refund->setRelation('payment', $locked), $actor->id);
        PaymentRefunded::dispatch($locked);

        return $refund;
    }

    private function audit(Payment $payment, Refund $refund): void
    {
        $this->audit->record(new AuditEntry(AuditEvent::RefundRecorded, $refund, [], [
            'payment_reference' => $payment->reference,
            'amount' => $refund->amount,
            'currency' => $refund->currency,
            'refunded_on' => $refund->refunded_on->toDateString(),
            'transfer_reference' => $refund->transfer_reference,
            'evidence_file_id' => $refund->evidence_file_id,
        ]));
        $this->audit->record(new AuditEntry(AuditEvent::PaymentRefunded, $payment, ['status' => PaymentStatus::Paid->value], ['status' => PaymentStatus::Refunded->value]));
    }
}
