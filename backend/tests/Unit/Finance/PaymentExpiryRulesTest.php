<?php

declare(strict_types=1);

use App\Enums\Finance\PaymentStatus;
use App\Models\Finance\Payment;
use App\Services\Finance\PaymentExpiry;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

it('cancels an unbound order without an intent at once', function (): void {
    fakePaymentGateway();
    $payment = Payment::factory()->create(['expires_at' => now()->subMinute()]);

    app(PaymentExpiry::class)->expire($payment);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Cancelled);
});

it('ends the grace window of a pending payment exactly 15 minutes after the expiry', function (): void {
    $gateway = fakePaymentGateway();
    $payment = Payment::factory()->create(['status' => PaymentStatus::Pending, 'provider_payment_id' => 'pay_grace_0001', 'bound_at' => now(), 'expires_at' => now()->subMinutes(14)]);
    providerPaymentOf($gateway, $payment, 'initiated', null, null, 'pay_grace_0001');

    app(PaymentExpiry::class)->expire($payment);
    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);

    $this->travel(1)->minute();
    app(PaymentExpiry::class)->expire($payment);
    expect($payment->refresh()->status)->toBe(PaymentStatus::Cancelled);
});

it('leaves concluded payments alone', function (PaymentStatus $status): void {
    $gateway = fakePaymentGateway();
    $payment = Payment::factory()->paid()->create(['expires_at' => now()->subHour()]);
    $payment->forceFill(['status' => $status, 'refunded_at' => now()])->save();

    app(PaymentExpiry::class)->expire($payment);

    expect($payment->refresh()->status)->toBe($status)->and($gateway->fetchCount())->toBe(0);
})->with([PaymentStatus::Paid, PaymentStatus::Refunded]);
