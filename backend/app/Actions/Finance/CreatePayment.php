<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Finance\CreatedPayment;
use App\Data\Finance\OpenedOrder;
use App\Enums\Finance\CancellationCause;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Integrations\IntegrationKind;
use App\Enums\Integrations\IntegrationMode;
use App\Exceptions\Finance\PaymentRejected;
use App\Exceptions\Integrations\ProviderCallFailed;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Services\Finance\CheckoutOpener;
use App\Services\Finance\CheckoutPresenter;
use App\Services\Finance\FinanceSettings;
use App\Services\Finance\PayingEntityGuard;
use App\Services\Finance\PaymentCancellation;
use App\Services\Finance\PaymentOrderOpener;
use App\Services\Finance\PaymentRowLock;
use App\Services\Finance\PriceCalculator;
use App\Services\Integrations\IntegrationCredentialsResolver;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final readonly class CreatePayment
{
    public function __construct(
        private PayingEntityGuard $entities,
        private IntegrationCredentialsResolver $credentials,
        private FinanceSettings $settings,
        private PriceCalculator $prices,
        private PaymentOrderOpener $orders,
        private CheckoutOpener $checkout,
        private CheckoutPresenter $presenter,
        private PaymentCancellation $cancellation,
        private PaymentRowLock $locks,
    ) {}

    public function handle(User $actor, PaymentPurpose $purpose, string $idempotencyKey, string $locale): CreatedPayment
    {
        $replay = $this->replayOf($actor, $purpose, $idempotencyKey);

        if ($replay instanceof Payment) {
            return $this->present($replay, $locale, true, false);
        }

        $entity = $this->entities->entityOf($actor);
        $mode = $this->gatewayMode();

        if (! in_array($purpose->value, $this->settings->linkedPaths(), true)) {
            throw PaymentRejected::purposeNotAvailable();
        }

        $quote = $this->prices->quote($purpose);

        try {
            $order = DB::transaction(fn (): OpenedOrder => $this->orders->open($actor, $entity, $purpose, $quote, $mode, $idempotencyKey));
        } catch (UniqueConstraintViolationException) {
            return $this->present($this->replayOf($actor, $purpose, $idempotencyKey) ?? throw PaymentRejected::checkoutInProgress(), $locale, true, false);
        }

        $this->openCheckout($order->payment);

        return $this->present($order->payment, $locale, false, $order->isExisting);
    }

    private function gatewayMode(): IntegrationMode
    {
        $credentials = $this->credentials->resolve(IntegrationKind::Payment);

        if (! $credentials->isConfigured()) {
            throw PaymentRejected::gatewayNotConfigured();
        }

        return $credentials->mode;
    }

    private function openCheckout(Payment $payment): void
    {
        try {
            $this->checkout->open($payment);
        } catch (ProviderCallFailed $failure) {
            DB::transaction(function () use ($payment): void {
                $locked = $this->locks->lock($payment);

                if ($locked->status === PaymentStatus::Created && $locked->provider_intent_id === null) {
                    $this->cancellation->cancel($locked, CancellationCause::CheckoutUnavailable);
                }
            });

            throw $failure;
        }
    }

    private function replayOf(User $actor, PaymentPurpose $purpose, string $idempotencyKey): ?Payment
    {
        $payment = Payment::query()->with(['entity', 'receipt'])->where('initiated_by_user_id', $actor->id)->where('idempotency_key', $idempotencyKey)->first();

        if ($payment instanceof Payment && $payment->purpose !== $purpose) {
            throw PaymentRejected::idempotencyKeyReused();
        }

        return $payment;
    }

    private function present(Payment $payment, string $locale, bool $isReplay, bool $isExisting): CreatedPayment
    {
        $payment->loadMissing(['entity', 'receipt']);

        return new CreatedPayment($payment, $this->presenter->present($payment, $locale), $isReplay, $isExisting);
    }
}
