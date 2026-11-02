<?php

declare(strict_types=1);

namespace App\Listeners\Finance;

use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Finance\ReceiptIssued;
use App\Services\Entities\EntityNotifier;
use App\Support\Finance\FinanceNotices;

final readonly class NotifyReceiptIssued
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(ReceiptIssued $event): void
    {
        $receipt = $event->receipt;
        $payment = $receipt->payment;

        $this->notifier->toOwner($payment->entity, FinanceNotices::forPayment(NotificationCode::PaymentReceiptIssued, FinanceNotices::FOR_ENTITY, $payment));

        if (! $receipt->is_test) {
            $this->notifier->toRole(RoleKey::FinanceOfficer, FinanceNotices::forPayment(NotificationCode::PaymentReceiptIssued, FinanceNotices::FOR_STAFF, $payment), null);
        }
    }
}
