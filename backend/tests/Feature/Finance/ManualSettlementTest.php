<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Finance\PaymentFailureReason;
use App\Enums\Finance\PaymentStatus;
use App\Models\Audit\Audit;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentReceipt;
use App\Notifications\Identity\SecurityAlertNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

const SETTLE_REASON = 'تم التحقق من الدفع في لوحة ميسر بعد تعطل الإشعار الآلي للبوابة.';

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

function settleEndpoint(Payment $payment): string
{
    return "/api/v1/finance/payments/{$payment->public_id}/manual-settle";
}

it('lets the Super Admin mark an open payment paid with a reason, issuing its receipt and raising SEC-04', function (): void {
    ['owner' => $owner] = payingEntity();
    $payment = openedPayment($owner);
    $superAdmin = superAdmin();

    switchTo($superAdmin)->postJson(settleEndpoint($payment), ['status' => 'paid', 'provider_payment_id' => 'pay_manual_0001', 'reason' => SETTLE_REASON])
        ->assertOk()
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.manually_settled', true)
        ->assertJsonPath('data.provider_payment_id', 'pay_manual_0001')
        ->assertJsonPath('data.receipt.status', 'issued');

    expect($payment->refresh()->manually_settled_at)->not->toBeNull()
        ->and(PaymentReceipt::query()->count())->toBe(1)
        ->and(Audit::query()->where('event', AuditEvent::PaymentManuallySettled->value)->value('reason'))->toBe(SETTLE_REASON);

    Notification::assertSentTo($superAdmin, SecurityAlertNotification::class);
});

it('marks an open bound payment failed', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $payment = openedPayment($owner);
    verifyAs($owner, $payment, providerPaymentOf($gateway, $payment, 'initiated'))->assertAccepted();

    switchTo(superAdmin())->postJson(settleEndpoint($payment), ['status' => 'failed', 'reason' => SETTLE_REASON])
        ->assertOk()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.failure_reason', PaymentFailureReason::SettledManually->value);
});

it('needs a gateway reference to mark an unbound order paid and a reason', function (): void {
    ['owner' => $owner] = payingEntity();
    $payment = openedPayment($owner);
    switchTo(superAdmin());

    $this->postJson(settleEndpoint($payment), ['status' => 'paid', 'reason' => SETTLE_REASON])->assertUnprocessable()->assertJsonPath('code', 'payment.provider_payment_id_required');
    $this->postJson(settleEndpoint($payment), ['status' => 'paid', 'provider_payment_id' => 'pay_manual_0001'])->assertUnprocessable()->assertJsonValidationErrors('reason');
    $this->postJson(settleEndpoint($payment), ['status' => 'refunded', 'reason' => SETTLE_REASON])->assertUnprocessable()->assertJsonValidationErrors('status');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Created);
});

it('refuses concluded payments', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $payment = paidPayment($owner, $gateway);

    switchTo(superAdmin())->postJson(settleEndpoint($payment), ['status' => 'failed', 'reason' => SETTLE_REASON])
        ->assertConflict()
        ->assertJsonPath('code', 'state.transition_illegal');
});

it('is reserved for the Super Admin', function (): void {
    ['owner' => $owner] = payingEntity();
    $payment = openedPayment($owner);

    foreach ([systemAdmin(), financeOfficer(), $owner] as $user) {
        switchTo($user)->postJson(settleEndpoint($payment), ['status' => 'paid', 'provider_payment_id' => 'pay_manual_0001', 'reason' => SETTLE_REASON])->assertForbidden();
    }
});
