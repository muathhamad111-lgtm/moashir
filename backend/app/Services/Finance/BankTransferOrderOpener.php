<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Data\Audit\AuditEntry;
use App\Data\Finance\BankTransferSubmission;
use App\Data\Finance\OrderTerms;
use App\Enums\Audit\AuditEvent;
use App\Enums\Finance\PaymentChannel;
use App\Events\Finance\BankTransferSubmitted;
use App\Exceptions\Finance\BankAccountRejected;
use App\Models\Entities\Entity;
use App\Models\Finance\BankAccount;
use App\Models\Finance\BankTransfer;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\Date;

final readonly class BankTransferOrderOpener
{
    public function __construct(private PaymentOrderOpener $orders, private BankTransferMode $mode, private AuditRecorder $audit) {}

    public function open(User $actor, Entity $entity, BankTransferSubmission $submission): Payment
    {
        $proof = $submission->proof;
        $account = $this->selectableAccount($submission->bankAccountId);
        $terms = new OrderTerms($submission->purpose, PaymentChannel::BankTransfer, $submission->quote, $this->mode->current(), $submission->idempotencyKey, $submission->cycle);
        $payment = $this->orders->open($actor, $entity, $terms)->payment;

        $transfer = BankTransfer::query()->create([
            'payment_id' => $payment->id,
            'bank_account_id' => $account->id,
            'proof_file_id' => $proof->id,
            'submitted_by_user_id' => $actor->id,
            'submitted_at' => Date::now(),
        ]);
        $proof->attachable()->associate($transfer)->save();
        $payment->setRelation('bankTransfer', $transfer->setRelation('bankAccount', $account)->setRelation('proofFile', $proof));

        $this->audit->record(new AuditEntry(AuditEvent::BankTransferSubmitted, $payment, [], [
            'reference' => $payment->reference,
            'bank_account_id' => $account->public_id,
            'proof_file_id' => $proof->public_id,
            'proof_sha256' => $proof->sha256,
            'total' => $payment->total,
        ]));

        BankTransferSubmitted::dispatch($payment);

        return $payment;
    }

    private function selectableAccount(string $publicId): BankAccount
    {
        $account = BankAccount::query()->where('public_id', $publicId)->sharedLock()->first();

        if (! $account instanceof BankAccount || ! $account->isSelectable()) {
            throw BankAccountRejected::notSelectable();
        }

        return $account;
    }
}
