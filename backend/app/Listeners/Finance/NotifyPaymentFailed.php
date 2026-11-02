<?php

declare(strict_types=1);

namespace App\Listeners\Finance;

use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Finance\PaymentFailed;
use App\Services\Entities\EntityNotifier;
use App\Support\Finance\FinanceNotices;

final readonly class NotifyPaymentFailed
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(PaymentFailed $event): void
    {
        $payment = $event->payment;

        $this->notifier->toOwner($payment->entity, FinanceNotices::forPayment(NotificationCode::PaymentFailed, FinanceNotices::FOR_ENTITY, $payment));
        $this->notifier->toRole(RoleKey::SystemAdmin, FinanceNotices::forPayment(NotificationCode::PaymentFailed, FinanceNotices::FOR_STAFF, $payment), null);
    }
}
