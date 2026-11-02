<?php

declare(strict_types=1);

namespace App\Listeners\Finance;

use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Finance\RefundRecorded;
use App\Services\Entities\EntityNotifier;
use App\Support\Finance\FinanceNotices;

final readonly class NotifyRefundRecorded
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(RefundRecorded $event): void
    {
        $payment = $event->refund->payment;
        $staffNotice = FinanceNotices::forPayment(NotificationCode::PaymentRefundRecorded, FinanceNotices::FOR_STAFF, $payment);

        $this->notifier->toOwner($payment->entity, FinanceNotices::forPayment(NotificationCode::PaymentRefundRecorded, FinanceNotices::FOR_ENTITY, $payment));
        $this->notifier->toRole(RoleKey::SystemAdmin, $staffNotice, $event->actorId);
        $this->notifier->toRole(RoleKey::FinanceOfficer, $staffNotice, $event->actorId);
    }
}
