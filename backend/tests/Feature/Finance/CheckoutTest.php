<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Entities\EntityStatus;
use App\Enums\Finance\PaymentFailureReason;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Identity\RoleKey;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\PaymentLinkedPath;
use App\Enums\Integrations\ProviderErrorKind;
use App\Enums\Notifications\NotificationCode;
use App\Enums\Settings\SettingKey;
use App\Models\Audit\Audit;
use App\Models\Entities\Entity;
use App\Models\Finance\CheckoutIntent;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentEvent;
use App\Notifications\Entities\EntityNotification;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

it('opens a payment order with the A1 checkout object of a server-side intent', function (): void {
    ['gateway' => $gateway, 'owner' => $owner, 'entity' => $entity] = payingEntity();

    $response = startPayment($owner)->assertCreated()
        ->assertJsonPath('meta.idempotent_replay', false)
        ->assertJsonPath('meta.existing_order', false)
        ->assertJsonPath('data.status', 'created')
        ->assertJsonPath('data.purpose', 'accredited')
        ->assertJsonPath('data.is_test', true)
        ->assertJsonPath('data.pricing', ['price_before_vat' => '1000.00', 'vat_rate' => '15.00', 'vat_amount' => '150.00', 'total' => '1150.00', 'currency' => 'SAR'])
        ->assertJsonPath('data.receipt', null)
        ->assertJsonPath('data.checkout.pattern', 'intent')
        ->assertJsonPath('data.checkout.mode', 'test')
        ->assertJsonPath('data.checkout.publishable_key', sprintf(FAKE_MOYASAR_PUBLISHABLE, 'test'))
        ->assertJsonPath('data.checkout.amount_halalas', 115000)
        ->assertJsonPath('data.checkout.currency', 'SAR')
        ->assertJsonPath('data.checkout.language', 'ar')
        ->assertJsonPath('data.checkout.methods', ['creditcard'])
        ->assertJsonPath('data.checkout.supported_networks', ['mada', 'visa', 'mastercard'])
        ->assertJsonPath('data.checkout.payment_intent', ['id' => 'fake-intent-1', 'client_secret' => 'fake-intent-secret-1'])
        ->assertJsonPath('data.checkout.apple_pay', null);

    $payment = Payment::query()->sole();
    $metadata = $response->json('data.checkout.metadata');

    expect($response->json('data.id'))->toBe($payment->public_id)
        ->and($payment->reference)->toMatch('/^PAY-\d{4}-\d{6}$/')
        ->and($metadata)->toBe(['moashir_payment_id' => $payment->public_id, 'moashir_reference' => $payment->reference, 'purpose' => 'accredited', 'app_env' => 'testing'])
        ->and($response->json('data.checkout.callback_url'))->toBe('http://localhost/payments/'.$payment->public_id.'/return')
        ->and($response->json('data.checkout.description'))->toBe('Moashir '.$payment->reference)
        ->and($payment->expires_at->diffInMinutes(Date::now(), true))->toBeGreaterThan(29)->toBeLessThan(31)
        ->and($payment->entity_name->ar)->toBe($entity->name->ar)
        ->and($payment->provider_intent_id)->toBe('fake-intent-1')
        ->and($gateway->checkouts()[0]->amount->amount)->toBe(ACCREDITED_TOTAL)
        ->and($gateway->checkouts()[0]->metadata['moashir_payment_id'])->toBe($payment->public_id)
        ->and(CheckoutIntent::query()->where('entity_id', $entity->id)->whereNull('converted_payment_id')->count())->toBe(1)
        ->and(PaymentEvent::query()->where('payment_id', $payment->id)->value('event_type'))->toBe('intent_created');

    expect((string) json_encode(Audit::query()->get(['old_values', 'new_values'])->toArray()))->not->toContain('fake-intent-secret-1')
        ->and((string) json_encode(PaymentEvent::query()->get()->toArray()))->not->toContain('fake-intent-secret-1')
        ->and(Audit::query()->where('event', AuditEvent::PaymentCreated->value)->count())->toBe(1);

    Notification::assertSentTo($owner, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::PaymentStarted);
});

it('prices the Kit from its own setting and offers Apple Pay with the label of the request locale', function (): void {
    ['owner' => $owner] = payingEntity();
    setIntegrationSetting(SettingKey::PaymentMethods, ['creditcard', 'applepay']);
    setIntegrationSetting(SettingKey::PaymentApplePayLabel, ['ar' => 'منصة مؤشر', 'en' => 'Moashir Platform']);

    switchTo($owner)->postJson('/api/v1/payments', ['purpose' => 'bag'], ['Idempotency-Key' => (string) Str::uuid(), 'Accept-Language' => 'en'])
        ->assertCreated()
        ->assertJsonPath('data.pricing.total', BAG_TOTAL)
        ->assertJsonPath('data.checkout.amount_halalas', 287500)
        ->assertJsonPath('data.checkout.language', 'en')
        ->assertJsonPath('data.checkout.apple_pay', ['country' => 'SA', 'label' => 'Moashir Platform', 'validate_merchant_url' => 'https://api.moyasar.com/v1/applepay/initiate']);
});

it('replays the stored order for a repeated idempotency key without a second intent', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $key = (string) Str::uuid();

    $first = startPayment($owner, 'accredited', $key)->assertCreated();
    startPayment($owner, 'accredited', mb_strtoupper($key))->assertCreated()
        ->assertJsonPath('meta.idempotent_replay', true)
        ->assertJsonPath('data.id', $first->json('data.id'))
        ->assertJsonPath('data.checkout.payment_intent.id', 'fake-intent-1');

    expect(Payment::query()->count())->toBe(1)->and($gateway->checkouts())->toHaveCount(1);

    startPayment($owner, 'bag', $key)->assertUnprocessable()->assertJsonPath('code', 'idempotency_key.reused');
});

it('returns the open order of the same service instead of opening a second one', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();

    $first = startPayment($owner)->assertCreated();
    startPayment($owner)->assertOk()
        ->assertJsonPath('meta.existing_order', true)
        ->assertJsonPath('data.id', $first->json('data.id'));

    startPayment($owner, 'bag')->assertCreated();

    expect(Payment::query()->count())->toBe(2)->and($gateway->checkouts())->toHaveCount(2);
});

it('requires a UUID idempotency key', function (?string $key): void {
    ['owner' => $owner] = payingEntity();
    $headers = $key === null ? [] : ['Idempotency-Key' => $key];

    switchTo($owner)->postJson('/api/v1/payments', ['purpose' => 'accredited'], $headers)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'idempotency_key.missing');

    expect(Payment::query()->count())->toBe(0);
})->with([
    'missing' => [null],
    'not a uuid' => ['retry-1'],
]);

it('rejects services that are not paid through the gateway', function (string $purpose, string $code): void {
    ['owner' => $owner] = payingEntity();
    setIntegrationSetting(SettingKey::PaymentLinkedPaths, [PaymentLinkedPath::Accredited->value]);

    startPayment($owner, $purpose)->assertUnprocessable()->assertJsonPath('code', $code);
})->with([
    'leadership path' => ['leadership', 'purpose.not_available'],
    'Kit not linked' => ['bag', 'purpose.not_available'],
    'unknown purpose' => ['membership', 'validation_failed'],
]);

it('answers 503 while no payment gateway is configured', function (): void {
    fakePaymentGateway();
    setServicePrices();
    $owner = ownerOf(entityWithOwner());

    startPayment($owner)->assertStatus(503)->assertJsonPath('code', 'integration.not_configured');

    expect(Payment::query()->count())->toBe(0);
});

it('answers 409 while the price of the service is not set', function (): void {
    ['owner' => $owner] = payingEntity();
    setServicePrices(null, null);

    startPayment($owner)->assertConflict()->assertJsonPath('code', 'payment.price_not_configured');
});

it('cancels the new order and answers 503 when the gateway cannot create the intent', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $gateway->fail('create', ProviderErrorKind::Unavailable);

    startPayment($owner)->assertStatus(503)->assertJsonPath('code', 'provider_unavailable');

    $payment = Payment::query()->sole();
    expect($payment->status)->toBe(PaymentStatus::Cancelled)
        ->and($payment->failure_reason)->toBe(PaymentFailureReason::CheckoutUnavailable);

    Notification::assertNotSentTo($owner, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::PaymentOrderCancelled);

    $gateway->recover('create');
    startPayment($owner)->assertCreated();
});

it('refuses a deactivated entity', function (): void {
    ['owner' => $owner, 'entity' => $entity] = payingEntity();
    $entity->forceFill(['status' => EntityStatus::Deactivated, 'deactivated_at' => Date::now(), 'deactivation_reason' => FREEZE_REASON])->save();

    startPayment($owner)->assertConflict()->assertJsonPath('code', 'payment.entity_not_active');
});

it('refuses a soft-deleted entity as frozen', function (): void {
    ['owner' => $owner, 'entity' => $entity] = payingEntity();
    Entity::query()->whereKey($entity->id)->update(['deleted_at' => Date::now()]);

    startPayment($owner)->assertConflict()->assertJsonPath('code', 'entity.deleted');
});

it('lets only entity accounts open payment orders', function (): void {
    payingEntity();

    foreach ([systemAdmin(), financeOfficer(), staffUser(RoleKey::RequestFollower)] as $staff) {
        startPayment($staff)->assertForbidden();
    }

    $this->flushSession();
    app('auth')->forgetGuards();
    $this->postJson('/api/v1/payments', ['purpose' => 'accredited'], ['Idempotency-Key' => (string) Str::uuid()])->assertUnauthorized();
});

it('records the live mode on orders opened while the live gateway is active', function (): void {
    ['owner' => $owner] = payingEntity(IntegrationMode::Live);

    startPayment($owner)->assertCreated()->assertJsonPath('data.is_test', false)->assertJsonPath('data.checkout.mode', 'live');
});
