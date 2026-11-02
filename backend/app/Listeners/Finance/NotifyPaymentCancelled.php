<?php

declare(strict_types=1);

namespace App\Listeners\Finance;

use App\Enums\Finance\CancellationCause;
use App\Enums\Notifications\NotificationCode;
use App\Events\Finance\PaymentCancelled;
use App\Services\Entities\EntityNotifier;
use App\Support\Finance\FinanceNotices;

final readonly class NotifyPaymentCancelled
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(PaymentCancelled $event): void
    {
        if ($event->cause !== CancellationCause::Expired) {
            return;
        }

        $payment = $event->payment;

        $this->notifier->toOwner($payment->entity, FinanceNotices::forPayment(NotificationCode::PaymentOrderCancelled, FinanceNotices::FOR_ENTITY, $payment)->inAppOnly());
    }
}
