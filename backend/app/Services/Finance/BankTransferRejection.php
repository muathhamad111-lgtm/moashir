<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Data\Audit\AuditEntry;
use App\Data\Finance\TransferRejection;
use App\Enums\Audit\AuditEvent;
use App\Enums\Finance\PaymentStatus;
use App\Events\Finance\BankTransferRejected;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class BankTransferRejection
{
    public const string VERB = 'reject_transfer';

    public function __construct(
        private ReviewableTransferLock $locks,
        private PaymentStateMachine $stateMachine,
        private AuditRecorder $audit,
    ) {}

    public function reject(User $actor, Payment $payment, TransferRejection $rejection): Payment
    {
        return DB::transaction(function () use ($actor, $payment, $rejection): Payment {
            $locked = $this->locks->lock($payment, self::VERB);
            $transfer = $this->locks->transferOf($locked);

            $transfer->forceFill([
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => Date::now(),
                'rejection_reason' => $rejection->reason,
                'review_note' => $rejection->note,
            ])->save();
            $this->stateMachine->transition($locked, PaymentStatus::Rejected, self::VERB);

            $this->audit->record(new AuditEntry(AuditEvent::BankTransferRejected, $locked, ['status' => PaymentStatus::UnderReview->value], [
                'status' => PaymentStatus::Rejected->value,
                'reference' => $locked->reference,
                'rejection_reason' => $rejection->reason->value,
                'review_note' => $rejection->note,
            ]));

            BankTransferRejected::dispatch($locked);

            return $locked;
        });
    }
}
