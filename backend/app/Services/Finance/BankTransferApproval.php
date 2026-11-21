<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class BankTransferApproval
{
    public const string VERB = 'approve_transfer';

    public function __construct(
        private ReviewableTransferLock $locks,
        private PaymentSettlement $settlement,
        private AuditRecorder $audit,
    ) {}

    public function approve(User $actor, Payment $payment): Payment
    {
        return DB::transaction(function () use ($actor, $payment): Payment {
            $locked = $this->locks->lock($payment, self::VERB);
            $transfer = $this->locks->transferOf($locked);

            $transfer->forceFill(['reviewed_by_user_id' => $actor->id, 'reviewed_at' => Date::now()])->save();
            $this->settlement->markPaid($locked, null, null);

            $this->audit->record(new AuditEntry(AuditEvent::BankTransferApproved, $locked, ['status' => 'under_review'], [
                'status' => $locked->status->value,
                'reference' => $locked->reference,
                'bank_account_id' => $transfer->bankAccount->public_id,
                'total' => $locked->total,
            ]));

            return $locked;
        });
    }
}
