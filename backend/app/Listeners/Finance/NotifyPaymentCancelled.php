<?php

declare(strict_types=1);

namespace App\Listeners\Finance;

use App\Enums\Finance\CancellationCause;
use App\Enums\Notifications\NotificationCode;
use App\Events\Finance\PaymentCancelled;
use App\Services\Entities\EntityNotifier;
use App\Services\Finance\PaymentAlerts;
use App\Support\Finance\FinanceNotices;

final readonly class NotifyPaymentCancelled
{
    public function __construct(private EntityNotifier $notifier, private PaymentAlerts $alerts) {}

    public function handle(PaymentCancelled $event): void
    {
        $payment = $event->payment;

        if ($event->cause === CancellationCause::EntityErased) {
            $this->alerts->transferRefundRequired($payment);

            return;
        }

        if ($event->cause !== CancellationCause::Expired) {
            return;
        }

        $this->notifier->toOwner($payment->entity, FinanceNotices::forPayment(NotificationCode::PaymentOrderCancelled, FinanceNotices::FOR_ENTITY, $payment)->inAppOnly());
    }
}
