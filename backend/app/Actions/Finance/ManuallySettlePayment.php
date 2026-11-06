<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Audit\AuditEntry;
use App\Data\Finance\ManualSettlement;
use App\Enums\Audit\AuditEvent;
use App\Enums\Finance\PaymentFailureReason;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Notifications\NotificationCode;
use App\Exceptions\Finance\PaymentRejected;
use App\Exceptions\IllegalStateTransition;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Finance\PaymentBinder;
use App\Services\Finance\PaymentEventRecorder;
use App\Services\Finance\PaymentFailure;
use App\Services\Finance\PaymentRowLock;
use App\Services\Finance\PaymentSettlement;
use App\Services\Notifications\SecurityAlerter;
use App\Support\RequestContext;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class ManuallySettlePayment
{
    public const string VERB = 'manual_settle';

    public function __construct(
        private PaymentRowLock $locks,
        private PaymentBinder $binder,
        private PaymentSettlement $settlement,
        private PaymentFailure $failure,
        private PaymentEventRecorder $events,
        private RequestContext $context,
        private SecurityAlerter $alerter,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, Payment $payment, ManualSettlement $settlement): Payment
    {
        $this->context->recordReason($settlement->reason);

        DB::transaction(fn () => $this->settle($actor, $this->locks->lock($payment), $settlement));
        $this->alerter->raise(NotificationCode::ReasonRequiredAction);

        return $payment->refresh()->load('receipt');
    }

    private function settle(User $actor, Payment $locked, ManualSettlement $settlement): void
    {
        if ($locked->isBankTransfer()) {
            throw PaymentRejected::channelNotSupported();
        }

        if (! $locked->status->isOpen()) {
            throw IllegalStateTransition::from($locked->status->value, self::VERB);
        }

        $previous = $locked->status;
        $providerPaymentId = $settlement->providerPaymentId ?? $locked->provider_payment_id;

        if ($settlement->marksPaid() && $providerPaymentId === null) {
            throw PaymentRejected::providerPaymentIdRequired();
        }

        if ($providerPaymentId !== null) {
            $this->binder->bind($locked, $providerPaymentId);
        }

        $locked->forceFill(['manually_settled_at' => Date::now()]);

        if ($settlement->marksPaid()) {
            $this->settlement->markPaid($locked, null, null);
        } else {
            $this->failure->markFailed($locked, PaymentFailureReason::SettledManually);
        }

        $this->events->recordAdmin($locked, 'manual_settle', $actor, ['status' => $settlement->status->value]);
        $this->audit->record(new AuditEntry(AuditEvent::PaymentManuallySettled, $locked, ['status' => $previous->value], [
            'status' => $locked->status->value,
            'provider_payment_id' => $locked->provider_payment_id,
        ]));
    }

    /**
     * @return list<string>
     */
    public static function allowedStatuses(): array
    {
        return [PaymentStatus::Paid->value, PaymentStatus::Failed->value];
    }
}
