<?php

declare(strict_types=1);

namespace App\Listeners\Finance;

use App\Enums\Identity\PermissionKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Finance\BankTransferSubmitted;
use App\Services\Entities\EntityNotifier;
use App\Support\Finance\FinanceNotices;

final readonly class NotifyBankTransferSubmitted
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(BankTransferSubmitted $event): void
    {
        $payment = $event->payment;

        $this->notifier->toOwner($payment->entity, FinanceNotices::forPayment(NotificationCode::BankTransferSubmitted, FinanceNotices::FOR_ENTITY, $payment));
        $this->notifier->toPermissionHolders(PermissionKey::FinanceTransfersReview, FinanceNotices::transferToReview($payment), null);
    }
}
