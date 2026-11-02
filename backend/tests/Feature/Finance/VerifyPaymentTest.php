<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Finance\PaymentEventResult;
use App\Enums\Finance\PaymentEventSource;
use App\Enums\Finance\PaymentInstrument;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Finance\ReceiptStatus;
use App\Enums\Identity\RoleKey;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\ProviderErrorKind;
use App\Enums\Notifications\NotificationCode;
use App\Models\Audit\Audit;
use App\Models\Finance\CheckoutIntent;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentEvent;
use App\Models\Finance\PaymentReceipt;
use App\Notifications\Entities\EntityNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

it('marks a verified paid payment as paid and issues a numbered receipt in live mode', function (): void {
    ['gateway' => $gateway, 'owner' => $owner, 'entity' => $entity] = payingEntity(IntegrationMode::Live);
    $admin = systemAdmin();
    $follower = staffUser(RoleKey::RequestFollower);
    $finance = financeOfficer();
    $payment = openedPayment($owner);
    $providerId = providerPaymentOf($gateway, $payment);

    verifyAs($owner, $payment, $providerId)->assertOk()
        ->assertJsonPath('meta.verification', 'applied')
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.method', 'mada')
        ->assertJsonPath('data.card_last4', '1111')
        ->assertJsonPath('data.receipt.status', 'issued')
        ->assertJsonPath('data.receipt.is_test', false);

    $payment->refresh();
    $receipt = PaymentReceipt::query()->sole();

    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->provider_payment_id)->toBe($providerId)
        ->and($payment->method)->toBe(PaymentInstrument::Mada)
        ->and($payment->checkout_client_secret)->toBeNull()
        ->and($receipt->number)->toMatch('/^RCP-\d{4}-000001$/')
        ->and($receipt->snapshot['total'])->toBe(ACCREDITED_TOTAL)
        ->and($receipt->snapshot['entity_name'])->toBe($entity->name->toArray())
        ->and($receipt->pdf_file_id)->not->toBeNull()
        ->and(CheckoutIntent::query()->where('converted_payment_id', $payment->id)->exists())->toBeTrue()
        ->and(PaymentEvent::query()->where('source', PaymentEventSource::Verification)->value('processing_result'))->toBe(PaymentEventResult::Applied)
        ->and(Audit::query()->where('event', AuditEvent::PaymentPaid->value)->count())->toBe(1)
        ->and(Audit::query()->where('event', AuditEvent::ReceiptIssued->value)->count())->toBe(1);

    expect(financeNoticeSent($owner, NotificationCode::PaymentSucceeded))->toBeTrue()
        ->and(financeNoticeSent($owner, NotificationCode::PaymentReceiptIssued))->toBeTrue()
        ->and(financeNoticeSent($follower, NotificationCode::PaymentSucceeded))->toBeTrue()
        ->and(financeNoticeSent($admin, NotificationCode::PaymentSucceeded))->toBeTrue()
        ->and(financeNoticeSent($finance, NotificationCode::PaymentReceiptIssued))->toBeTrue();
});

it('issues a TEST receipt without an official number for a test-mode payment', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $finance = financeOfficer();

    paidPayment($owner, $gateway);

    $receipt = PaymentReceipt::query()->sole();
    expect($receipt->is_test)->toBeTrue()->and($receipt->number)->toBeNull();
    Notification::assertNotSentTo($finance, EntityNotification::class);
});

it('keeps the order pending while the provider still reports it initiated', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $payment = openedPayment($owner);
    $providerId = providerPaymentOf($gateway, $payment, 'initiated');

    verifyAs($owner, $payment, $providerId)->assertAccepted()
        ->assertJsonPath('meta.verification', 'pending')
        ->assertJsonPath('data.status', 'pending');

    expect($payment->refresh()->provider_payment_id)->toBe($providerId)->and($payment->bound_at)->not->toBeNull();
});

it('records a declined payment with its reason group', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $admin = systemAdmin();
    $payment = openedPayment($owner);

    verifyAs($owner, $payment, providerPaymentOf($gateway, $payment, 'failed', null, '51'))->assertOk()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.failure_reason', 'insufficient_funds')
        ->assertJsonPath('data.receipt', null);

    expect($payment->refresh()->failure_code)->toBe('51')
        ->and(financeNoticeSent($owner, NotificationCode::PaymentFailed))->toBeTrue()
        ->and(financeNoticeSent($admin, NotificationCode::PaymentFailed))->toBeTrue();
});

it('fails a paid provider payment whose amount differs and alerts finance to refund it outside the platform', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $finance = financeOfficer();
    $payment = openedPayment($owner);

    verifyAs($owner, $payment, providerPaymentOf($gateway, $payment, 'paid', '1.00'))->assertOk()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.failure_reason', 'amount_mismatch');

    expect(PaymentReceipt::query()->count())->toBe(0)
        ->and(financeNoticeSent($finance, NotificationCode::PaymentRefundRequired))->toBeTrue();
});

it('rejects a provider payment created for another order', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $payment = openedPayment($owner);
    $foreignId = (string) Str::uuid();
    $gateway->recordPayment($foreignId, 'paid', $payment->totalMoney(), ['moashir_payment_id' => (string) Str::uuid()]);

    verifyAs($owner, $payment, $foreignId)->assertConflict()->assertJsonPath('code', 'payment.verification_rejected');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Created)
        ->and($payment->provider_payment_id)->toBeNull()
        ->and(Audit::query()->where('event', AuditEvent::PaymentVerificationRejected->value)->count())->toBe(1);
});

it('keeps the first provider payment bound to the order', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $payment = openedPayment($owner);
    verifyAs($owner, $payment, providerPaymentOf($gateway, $payment, 'initiated'))->assertAccepted();

    verifyAs($owner, $payment, providerPaymentOf($gateway, $payment))->assertConflict()->assertJsonPath('code', 'payment.binding_mismatch');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('answers with the current state when the payment is already concluded', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $payment = paidPayment($owner, $gateway);
    $fetches = $gateway->fetchCount();

    verifyAs($owner, $payment, (string) $payment->provider_payment_id)->assertOk()
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('meta.verification', 'ignored');

    expect($gateway->fetchCount())->toBe($fetches)->and(PaymentReceipt::query()->count())->toBe(1);
});

it('keeps a late paid order cancelled and alerts finance and the system administrators', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $admin = systemAdmin();
    $finance = financeOfficer();
    $payment = openedPayment($owner);
    $payment->forceFill(['status' => PaymentStatus::Cancelled, 'cancelled_at' => now(), 'checkout_client_secret' => null])->save();

    verifyAs($owner, $payment, providerPaymentOf($gateway, $payment))->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('meta.verification', 'late_payment');

    $payment->refresh();
    expect($payment->status)->toBe(PaymentStatus::Cancelled)
        ->and($payment->late_paid_at)->not->toBeNull()
        ->and(PaymentReceipt::query()->count())->toBe(0)
        ->and(Audit::query()->where('event', AuditEvent::PaymentLatePaid->value)->count())->toBe(1)
        ->and(financeNoticeSent($finance, NotificationCode::PaymentRefundRequired))->toBeTrue()
        ->and(financeNoticeSent($admin, NotificationCode::PaymentRefundRequired))->toBeTrue();
});

it('answers 422 for an unknown provider payment and 503 when the gateway is unreachable', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $payment = openedPayment($owner);

    $gateway->fail('fetch', ProviderErrorKind::Rejected);
    verifyAs($owner, $payment, 'pay_unknown_0001')->assertUnprocessable()->assertJsonPath('code', 'payment.provider_payment_not_found');

    $gateway->fail('fetch', ProviderErrorKind::Unavailable);
    verifyAs($owner, $payment, 'pay_unknown_0001')->assertStatus(503)->assertJsonPath('code', 'provider_unavailable');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Created);
});

it('validates the provider payment id', function (mixed $value): void {
    ['owner' => $owner] = payingEntity();
    $payment = openedPayment($owner);

    switchTo($owner)->postJson("/api/v1/payments/{$payment->public_id}/verify", ['provider_payment_id' => $value])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('provider_payment_id');
})->with([
    'missing' => [null],
    'with spaces' => ['pay 0001'],
    'too long' => [str_repeat('a', 81)],
]);

it('hides other entities payments and refuses staff on the entity endpoints', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $payment = openedPayment($owner);
    $other = ownerOf(entityWithOwner());

    verifyAs($other, $payment, 'pay_0001')->assertNotFound();
    switchTo($other)->getJson("/api/v1/payments/{$payment->public_id}")->assertNotFound();
    verifyAs(financeOfficer(), $payment, 'pay_0001')->assertForbidden();
    switchTo(systemAdmin())->getJson("/api/v1/payments/{$payment->public_id}")->assertForbidden();
    switchTo($owner)->getJson('/api/v1/payments/not-a-uuid')->assertNotFound();
});

it('settles through the real Moyasar adapter with the secret key of the payment mode', function (): void {
    activatePaymentGateway();
    setServicePrices();
    $owner = ownerOf(entityWithOwner());
    fakeProviderRoutes([
        'POST api.moyasar.com/v1/payment_intents' => Http::response(['id' => 'pi_fake_0001', 'status' => 'created', 'client_secret' => 'pi_fake_secret_0001', 'amount' => 115000, 'currency' => 'SAR', 'expires_at' => now()->addHour()->toIso8601String()], 201),
        'GET api.moyasar.com/v1/payments/*' => static fn () => Http::response([
            'id' => 'pay_fake_0001', 'status' => 'paid', 'amount' => 115000, 'currency' => 'SAR',
            'metadata' => ['moashir_payment_id' => Payment::query()->value('public_id')],
            'source' => ['type' => 'applepay', 'company' => 'visa', 'name' => 'Salman', 'number' => '4111-11XX-XXXX-4242'],
        ]),
    ]);

    $payment = openedPayment($owner);
    verifyAs($owner, $payment, 'pay_fake_0001')->assertOk()->assertJsonPath('data.status', 'paid')->assertJsonPath('data.method', 'applepay');

    Http::assertSent(static fn ($request): bool => str_ends_with($request->url(), '/v1/payment_intents')
        && $request['amount'] === 115000
        && $request['callback_url'] === 'http://localhost/payments/'.$payment->public_id.'/return');
    Http::assertSent(static fn ($request): bool => str_contains($request->url(), '/v1/payments/pay_fake_0001')
        && $request->hasHeader('Authorization', 'Basic '.base64_encode(sprintf(FAKE_MOYASAR_SECRET, 'test').':')));

    expect($payment->refresh()->card_last4)->toBe('4242')->and($payment->receipt?->status)->toBe(ReceiptStatus::Issued);
});
