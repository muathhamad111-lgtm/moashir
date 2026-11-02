<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\Finance\PaymentStatus;
use App\Exceptions\IllegalStateTransition;
use App\Models\Finance\Payment;
use Illuminate\Support\Facades\Date;

final class PaymentStateMachine
{
    public function assertAllows(Payment $payment, PaymentStatus $next, string $verb): void
    {
        if (! $payment->status->allowsTransitionTo($next)) {
            throw IllegalStateTransition::from($payment->status->value, $verb);
        }
    }

    public function transition(Payment $payment, PaymentStatus $next, string $verb): void
    {
        $this->assertAllows($payment, $next, $verb);

        $payment->status = $next;
        $this->stamp($payment, $next);

        if (! $next->isOpen()) {
            $payment->checkout_client_secret = null;
        }

        $payment->save();
    }

    private function stamp(Payment $payment, PaymentStatus $status): void
    {
        $column = match ($status) {
            PaymentStatus::Paid => 'paid_at',
            PaymentStatus::Failed => 'failed_at',
            PaymentStatus::Cancelled => 'cancelled_at',
            PaymentStatus::Refunded => 'refunded_at',
            PaymentStatus::Created, PaymentStatus::Pending => null,
        };

        if ($column !== null) {
            $payment->setAttribute($column, Date::now());
        }
    }
}
