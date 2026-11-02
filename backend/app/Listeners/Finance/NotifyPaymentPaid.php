<?php

declare(strict_types=1);

namespace App\Listeners\Finance;

use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Finance\PaymentPaid;
use App\Services\Entities\EntityNotifier;
use App\Support\Finance\FinanceNotices;

final readonly class NotifyPaymentPaid
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(PaymentPaid $event): void
    {
        $payment = $event->payment;
        $staffNotice = FinanceNotices::forPayment(NotificationCode::PaymentSucceeded, FinanceNotices::FOR_STAFF, $payment);

        $this->notifier->toOwner($payment->entity, FinanceNotices::forPayment(NotificationCode::PaymentSucceeded, FinanceNotices::FOR_ENTITY, $payment));
        $this->notifier->toRole(RoleKey::RequestFollower, $staffNotice, null);
        $this->notifier->toRole(RoleKey::SystemAdmin, $staffNotice, null);
    }
}
