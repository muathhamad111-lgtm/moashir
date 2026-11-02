<?php

declare(strict_types=1);

use App\Actions\Finance\ExpireDuePayments;
use App\Actions\Finance\ReconcilePendingPayments;
use App\Enums\Finance\PaymentEventSource;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Integrations\ProviderErrorKind;
use App\Enums\Notifications\NotificationCode;
use App\Enums\Settings\SettingKey;
use App\Jobs\Finance\ExpirePaymentsJob;
use App\Jobs\Finance\ReconcilePendingPaymentsJob;
use App\Models\Finance\PaymentEvent;
use App\Models\Finance\PaymentReceipt;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

function expireDuePayments(): int
{
    return app(ExpireDuePayments::class)->handle();
}

it('cancels the intent and the order at its expiry and tells the entity', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    setIntegrationSetting(SettingKey::PaymentOrderValidityMinutes, 10);
    $payment = openedPayment($owner);

    $this->travel(9)->minutes();
    expect(expireDuePayments())->toBe(0);

    $this->travel(2)->minutes();
    expect(expireDuePayments())->toBe(1)
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Cancelled)
        ->and($payment->checkout_client_secret)->toBeNull()
        ->and($gateway->cancelledIntents())->toBe(['fake-intent-1'])
        ->and(PaymentEvent::query()->where('source', PaymentEventSource::Expiry)->value('event_type'))->toBe('order_expired')
        ->and(financeNoticeSent($owner, NotificationCode::PaymentOrderCancelled))->toBeTrue();

    startPayment($owner)->assertCreated();
});

it('retries a later run when the intent cannot be cancelled because the gateway is unreachable', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $payment = openedPayment($owner);
    $gateway->fail('cancel', ProviderErrorKind::Unavailable);

    $this->travel(31)->minutes();
    expireDuePayments();
    expect($payment->refresh()->status)->toBe(PaymentStatus::Created);

    $gateway->recover('cancel');
    expireDuePayments();
    expect($payment->refresh()->status)->toBe(PaymentStatus::Cancelled);
});

it('cancels the order when the gateway refuses to cancel an intent that was already used', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $payment = openedPayment($owner);
    $gateway->fail('cancel', ProviderErrorKind::Rejected);

    $this->travel(31)->minutes();
    expireDuePayments();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Cancelled);
});

it('keeps a bound initiated payment pending during the grace window, then cancels it', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $payment = openedPayment($owner);
    verifyAs($owner, $payment, providerPaymentOf($gateway, $payment, 'initiated'))->assertAccepted();

    $this->travel(31)->minutes();
    expireDuePayments();
    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);

    $this->travel(15)->minutes();
    expireDuePayments();
    expect($payment->refresh()->status)->toBe(PaymentStatus::Cancelled);
});

it('settles a bound payment that completed before the expiry run', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $payment = openedPayment($owner);
    $providerId = providerPaymentOf($gateway, $payment, 'initiated');
    verifyAs($owner, $payment, $providerId)->assertAccepted();
    providerPaymentOf($gateway, $payment, 'paid', null, null, $providerId);

    $this->travel(31)->minutes();
    expireDuePayments();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and(PaymentReceipt::query()->count())->toBe(1);
});

it('keeps an order cancelled when the gateway later confirms the payment', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $finance = financeOfficer();
    $payment = openedPayment($owner);
    $this->travel(31)->minutes();
    expireDuePayments();

    sendMoyasarWebhook(moyasarEventFor($payment, providerPaymentOf($gateway, $payment)))->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Cancelled)
        ->and($payment->late_paid_at)->not->toBeNull()
        ->and(PaymentReceipt::query()->count())->toBe(0)
        ->and(financeNoticeSent($finance, NotificationCode::PaymentRefundRequired))->toBeTrue();
});

it('reconciles stale pending payments with the gateway', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $payment = openedPayment($owner);
    $providerId = providerPaymentOf($gateway, $payment, 'initiated');
    verifyAs($owner, $payment, $providerId)->assertAccepted();
    providerPaymentOf($gateway, $payment, 'failed', null, '05', $providerId);

    expect(app(ReconcilePendingPayments::class)->handle())->toBe(0);

    $this->travel(6)->minutes();
    app(ReconcilePendingPayments::class)->handle();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Failed)
        ->and(PaymentEvent::query()->where('source', PaymentEventSource::Reconciliation)->count())->toBe(1);
});

it('schedules the expiry and reconciliation jobs', function (): void {
    $events = collect(app(Schedule::class)->events())->map(static fn ($event): string => (string) $event->description);

    expect($events)->toContain(ExpirePaymentsJob::class)
        ->and($events)->toContain(ReconcilePendingPaymentsJob::class);
});
