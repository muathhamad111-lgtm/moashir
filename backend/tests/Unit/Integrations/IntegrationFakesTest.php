<?php

declare(strict_types=1);

use App\Data\Integrations\AiPrompt;
use App\Data\Integrations\PaymentCheckoutRequest;
use App\Data\Integrations\ProviderPayment;
use App\Data\Integrations\ResolvedCredentials;
use App\Data\Integrations\SmsMessage;
use App\Enums\Integrations\IntegrationKind;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\SmsPurpose;
use App\Integrations\Fakes\FakeAiProvider;
use App\Integrations\Fakes\FakePaymentGateway;
use App\Integrations\Fakes\FakeSmsGateway;
use App\Support\ValueObjects\Money;
use App\Support\ValueObjects\PhoneNumber;

it('offers a payment fake for the payments domain that records cancellations', function (): void {
    $gateway = new FakePaymentGateway;
    $session = $gateway->createCheckout(new PaymentCheckoutRequest(IntegrationMode::Test, new Money('5750.00'), 'Moashir ACC-2026-000014', 'https://moashir.example.sa/payments/abc/return'));
    $gateway->recordPayment('pay-1', 'paid', new Money('5750.00'), ['moashir_payment_id' => 'abc']);
    $gateway->cancelCheckout(IntegrationMode::Test, $session->intentId);

    expect($session->amount->amount)->toBe('5750.00')
        ->and($gateway->fetchPayment(IntegrationMode::Test, 'pay-1')->metadataValue('moashir_payment_id'))->toBe('abc')
        ->and($gateway->cancelledIntents())->toBe([$session->intentId])
        ->and(fn (): ProviderPayment => $gateway->fetchPayment(IntegrationMode::Test, 'unknown'))->toThrow(LogicException::class);
});

it('offers SMS and AI fakes that record what they receive', function (): void {
    $sms = new FakeSmsGateway;
    $sms->send(new SmsMessage(new PhoneNumber('+966551234567'), 'رمز التحقق 482915', SmsPurpose::Otp));
    $ai = new FakeAiProvider(IntegrationProvider::Anthropic);
    $credentials = ResolvedCredentials::none(IntegrationKind::Ai, IntegrationProvider::Anthropic, IntegrationMode::Test);
    $ai->answer($credentials, new AiPrompt('Answer briefly.', 'Connection test.', [], 16));

    expect($sms->sent())->toBe([['recipient' => '+966551234567', 'message' => 'رمز التحقق 482915', 'purpose' => 'otp']])
        ->and($ai->provider())->toBe(IntegrationProvider::Anthropic)
        ->and($ai->models($credentials)[0]->id)->toBe('fake-model-small')
        ->and($ai->prompts())->toHaveCount(1);
});
