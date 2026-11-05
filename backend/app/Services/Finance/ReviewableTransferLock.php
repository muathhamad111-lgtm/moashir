<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\Finance\PaymentStatus;
use App\Exceptions\Entities\EntityRejected;
use App\Exceptions\Finance\PaymentRejected;
use App\Exceptions\IllegalStateTransition;
use App\Models\Entities\Entity;
use App\Models\Finance\BankTransfer;
use App\Models\Finance\Payment;

final readonly class ReviewableTransferLock
{
    public function __construct(private PaymentRowLock $payments) {}

    public function lock(Payment $payment, string $verb): Payment
    {
        $entity = Entity::withTrashed()->whereKey($payment->entity_id)->lockForUpdate()->first();

        if (! $entity instanceof Entity || $entity->trashed()) {
            throw EntityRejected::deleted();
        }

        $locked = $this->payments->lock($payment);

        if (! $locked->isBankTransfer()) {
            throw PaymentRejected::channelNotSupported();
        }

        if ($locked->status !== PaymentStatus::UnderReview) {
            throw IllegalStateTransition::from($locked->status->value, $verb);
        }

        $locked->load(['bankTransfer.bankAccount', 'bankTransfer.proofFile']);

        return $locked;
    }

    public function transferOf(Payment $locked): BankTransfer
    {
        return $locked->bankTransfer ?? throw PaymentRejected::channelNotSupported();
    }
}
