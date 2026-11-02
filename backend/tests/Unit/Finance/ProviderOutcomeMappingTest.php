<?php

declare(strict_types=1);

use App\Data\Integrations\VerifiedPaymentEvent;
use App\Enums\Finance\PaymentFailureReason;
use App\Enums\Finance\PaymentInstrument;
use App\Enums\Finance\ProviderPaymentStatus;
use App\Enums\Finance\ProviderStatusEffect;
use App\Enums\Integrations\IntegrationMode;
use App\Models\Finance\Payment;
use App\Services\Finance\WebhookPaymentMatcher;

it('maps every Moyasar payment status to its effect', function (string $status, ProviderStatusEffect $effect): void {
    expect(ProviderPaymentStatus::effectOf($status))->toBe($effect);
})->with([
    ['initiated', ProviderStatusEffect::AwaitPayment],
    ['paid', ProviderStatusEffect::Settle],
    ['failed', ProviderStatusEffect::Fail],
    ['refunded', ProviderStatusEffect::ReportRefund],
    ['voided', ProviderStatusEffect::ReportRefund],
    ['authorized', ProviderStatusEffect::Unexpected],
    ['captured', ProviderStatusEffect::Unexpected],
    ['verified', ProviderStatusEffect::Unexpected],
    ['expired', ProviderStatusEffect::Unexpected],
    ['something_new', ProviderStatusEffect::Unexpected],
]);

it('maps the provider source to the payment method', function (?string $type, ?string $company, ?PaymentInstrument $method): void {
    expect(PaymentInstrument::fromSource($type, $company))->toBe($method);
})->with([
    ['creditcard', 'mada', PaymentInstrument::Mada],
    ['creditcard', 'MADA', PaymentInstrument::Mada],
    ['creditcard', 'visa', PaymentInstrument::CreditCard],
    ['creditcard', null, PaymentInstrument::CreditCard],
    ['applepay', 'visa', PaymentInstrument::ApplePay],
    ['stcpay', null, PaymentInstrument::StcPay],
    ['samsungpay', null, null],
    [null, null, null],
]);

it('groups declined response codes into reasons for the entity message', function (?string $code, PaymentFailureReason $reason): void {
    expect(PaymentFailureReason::fromResponseCode($code))->toBe($reason);
})->with([
    ['51', PaymentFailureReason::InsufficientFunds],
    ['05', PaymentFailureReason::Declined],
    ['54', PaymentFailureReason::ExpiredCard],
    ['41', PaymentFailureReason::LostOrStolen],
    ['43', PaymentFailureReason::LostOrStolen],
    ['61', PaymentFailureReason::LimitExceeded],
    ['99', PaymentFailureReason::Other],
    [null, PaymentFailureReason::Other],
]);

it('matches webhook events by our payment id first and by the provider payment id second', function (): void {
    $bound = Payment::factory()->create(['status' => 'pending', 'provider_payment_id' => 'pay_bound_0001', 'bound_at' => now()]);
    $open = Payment::factory()->create();
    $matcher = new WebhookPaymentMatcher;
    $event = static fn (?string $providerId, ?string $publicId): VerifiedPaymentEvent => new VerifiedPaymentEvent('evt-1', 'payment_paid', IntegrationMode::Test, $providerId, $publicId, []);

    expect($matcher->match($event('pay_new_0001', $open->public_id))?->id)->toBe($open->id)
        ->and($matcher->match($event('pay_bound_0001', null))?->id)->toBe($bound->id)
        ->and($matcher->match($event('pay_bound_0001', 'not-a-uuid'))?->id)->toBe($bound->id)
        ->and($matcher->match($event('pay_unknown_0001', '2f9d8a52-0000-4000-8000-000000000014')))->toBeNull()
        ->and($matcher->match($event(null, null)))->toBeNull();
});
