<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Contracts\Integrations\PaymentGateway;
use App\Enums\Finance\CancellationCause;
use App\Enums\Finance\PaymentStatus;
use App\Exceptions\Finance\PaymentRejected;
use App\Exceptions\IllegalStateTransition;
use App\Exceptions\Integrations\ProviderCallFailed;
use App\Models\Finance\Payment;
use App\Services\Finance\PaymentCancellation;
use App\Services\Finance\PaymentRowLock;
use Illuminate\Support\Facades\DB;

final readonly class CancelPaymentOrder
{
    public const string VERB = 'cancel';

    public function __construct(
        private PaymentGateway $gateway,
        private PaymentRowLock $locks,
        private PaymentCancellation $cancellation,
    ) {}

    public function handle(Payment $payment): Payment
    {
        $this->assertCancellable($payment);
        $this->cancelCheckout($payment);

        DB::transaction(function () use ($payment): void {
            $locked = $this->locks->lock($payment);
            $this->assertCancellable($locked);
            $this->cancellation->cancel($locked, CancellationCause::CancelledByEntity);
        });

        return $payment->refresh();
    }

    private function assertCancellable(Payment $payment): void
    {
        if ($payment->isBankTransfer()) {
            throw PaymentRejected::channelNotSupported();
        }

        if ($payment->status !== PaymentStatus::Created || $payment->isBound()) {
            throw IllegalStateTransition::from($payment->status->value, self::VERB);
        }
    }

    private function cancelCheckout(Payment $payment): void
    {
        if ($payment->provider_intent_id === null) {
            return;
        }

        try {
            $this->gateway->cancelCheckout($payment->mode, $payment->provider_intent_id);
        } catch (ProviderCallFailed $failure) {
            if ($failure->isTransient()) {
                throw $failure;
            }

            report($failure);
        }
    }
}
