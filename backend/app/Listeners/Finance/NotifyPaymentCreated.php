<?php

declare(strict_types=1);

namespace App\Listeners\Finance;

use App\Enums\Notifications\NotificationCode;
use App\Events\Finance\PaymentCreated;
use App\Services\Entities\EntityNotifier;
use App\Support\Finance\FinanceNotices;

final readonly class NotifyPaymentCreated
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(PaymentCreated $event): void
    {
        $payment = $event->payment;

        $this->notifier->toOwner($payment->entity, FinanceNotices::forPayment(NotificationCode::PaymentStarted, FinanceNotices::FOR_ENTITY, $payment)->inAppOnly());
    }
}
