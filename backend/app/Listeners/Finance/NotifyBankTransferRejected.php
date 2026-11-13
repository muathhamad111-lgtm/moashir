<?php

declare(strict_types=1);

namespace App\Listeners\Finance;

use App\Events\Finance\BankTransferRejected;
use App\Services\Entities\EntityNotifier;
use App\Support\Finance\FinanceNotices;

final readonly class NotifyBankTransferRejected
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(BankTransferRejected $event): void
    {
        $payment = $event->payment;

        $this->notifier->toOwner($payment->entity, FinanceNotices::transferRejected($payment));
    }
}
