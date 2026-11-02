<?php

declare(strict_types=1);

use App\Actions\Finance\ProcessPaymentWebhooks;
use App\Actions\Finance\ReconcilePendingPayments;
use App\Enums\Finance\PaymentEventResult;
use App\Enums\Finance\PaymentEventSource;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\ProviderErrorKind;
use App\Enums\Notifications\NotificationCode;
use App\Exceptions\Integrations\ProviderCallFailed;
use App\Jobs\Finance\ProcessGatewayWebhookJob;
use App\Models\Finance\PaymentEvent;
use App\Models\Finance\PaymentReceipt;
use App\Support\Integrations\IntegrationUrls;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

it('serves the webhook at the URL shown on the payment settings screen', function (): void {
    expect(route('webhooks.moyasar', [], false))->toBe(IntegrationUrls::MOYASAR_WEBHOOK_PATH);
});

it('stores a verified event without the secret or personal data and settles the payment by re-fetching it', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $payment = openedPayment($owner);
    $providerId = providerPaymentOf($gateway, $payment);

    sendMoyasarWebhook(moyasarEventFor($payment, $providerId))
        ->assertOk()
        ->assertJsonPath('data', ['received' => true, 'duplicate' => false]);

    $event = PaymentEvent::query()->where('source', PaymentEventSource::Webhook)->sole();
    $stored = (string) json_encode($event->payload);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and(PaymentReceipt::query()->count())->toBe(1)
        ->and($event->payment_id)->toBe($payment->id)
        ->and($event->signature_valid)->toBeTrue()
        ->and($event->processing_result)->toBe(PaymentEventResult::Applied)
        ->and($event->mode)->toBe(IntegrationMode::Test)
        ->and($stored)->not->toContain(FAKE_WEBHOOK_SECRET)
        ->and($stored)->not->toContain('Salman')
        ->and($stored)->not->toContain('62.149.100.20')
        ->and($event->payload['data']['source']['number'])->toBe('1111');
});

it('acknowledges a duplicate delivery once and does not process it again', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $payment = openedPayment($owner);
    $payload = moyasarEventFor($payment, providerPaymentOf($gateway, $payment));

    sendMoyasarWebhook($payload)->assertOk()->assertJsonPath('data.duplicate', false);
    $fetches = $gateway->fetchCount();
    sendMoyasarWebhook($payload)->assertOk()->assertJsonPath('data.duplicate', true);

    expect(PaymentEvent::query()->where('source', PaymentEventSource::Webhook)->count())->toBe(1)
        ->and($gateway->fetchCount())->toBe($fetches)
        ->and(PaymentReceipt::query()->count())->toBe(1);
});

it('trusts the re-fetched payment over the status named by the event', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $payment = openedPayment($owner);
    $providerId = providerPaymentOf($gateway, $payment, 'failed', null, '05');

    sendMoyasarWebhook(moyasarEventFor($payment, $providerId, 'payment_paid'))->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Failed)
        ->and($payment->failure_reason?->value)->toBe('declined')
        ->and(PaymentReceipt::query()->count())->toBe(0);
});

it('rejects an unauthenticated or malformed delivery without storing it', function (string $payload, int $status, string $code): void {
    ['owner' => $owner] = payingEntity();
    openedPayment($owner);

    sendMoyasarWebhook($payload)->assertStatus($status)->assertJsonPath('code', $code);

    expect(PaymentEvent::query()->where('source', PaymentEventSource::Webhook)->count())->toBe(0);
})->with([
    'wrong token' => [fn (): string => moyasarEventFor(null, 'pay_0001', 'payment_paid', [], ['secret_token' => 'not-the-shared-secret-0000000000000']), 401, 'webhook.unauthenticated'],
    'missing token' => [fn (): string => moyasarEventFor(null, 'pay_0001', 'payment_paid', [], ['secret_token' => null]), 401, 'webhook.unauthenticated'],
    'live event signed with the test secret' => [fn (): string => moyasarEventFor(null, 'pay_0001', 'payment_paid', [], ['live' => true]), 401, 'webhook.unauthenticated'],
    'not json' => ['payment_paid', 400, 'webhook.malformed'],
    'no event id' => [fn (): string => (string) json_encode(['type' => 'payment_paid', 'live' => false, 'secret_token' => FAKE_WEBHOOK_SECRET]), 400, 'webhook.malformed'],
]);

it('refuses bodies larger than 256 KB', function (): void {
    payingEntity();

    sendMoyasarWebhook(moyasarEventFor(null, 'pay_0001', 'payment_paid', ['padding' => str_repeat('a', 262_144)]))
        ->assertStatus(413)
        ->assertJsonPath('code', 'request.too_large');
});

it('needs neither a session, a CSRF token nor a JSON Accept header', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $payment = openedPayment($owner);
    $this->flushSession();
    app('auth')->forgetGuards();

    $this->call('POST', '/api/v1/webhooks/moyasar', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost', 'HTTP_REFERER' => 'http://localhost/'], moyasarEventFor($payment, providerPaymentOf($gateway, $payment)))
        ->assertOk();
});

it('records unmatched events and alerts only for unmatched live events', function (): void {
    payingEntity();
    configureMoyasar(IntegrationMode::Live, passedTestState());
    $admin = systemAdmin();

    sendMoyasarWebhook(moyasarEventFor(null, (string) Str::uuid()))->assertOk();
    Notification::assertNothingSentTo($admin);

    sendMoyasarWebhook(moyasarEventFor(null, (string) Str::uuid(), 'payment_paid', [], ['live' => true]))->assertOk();

    expect(PaymentEvent::query()->where('processing_result', PaymentEventResult::Unmatched)->count())->toBe(2)
        ->and(financeNoticeSent($admin, NotificationCode::ExternalServiceDown))->toBeTrue();
});

it('stores provider refund events without changing the payment and asks finance to record the refund', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $finance = financeOfficer();
    $payment = paidPayment($owner, $gateway);
    $gateway->recordPayment((string) $payment->provider_payment_id, 'refunded', $payment->totalMoney(), ['moashir_payment_id' => $payment->public_id]);

    sendMoyasarWebhook(moyasarEventFor($payment, (string) $payment->provider_payment_id, 'payment_refunded'))->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and(PaymentEvent::query()->where('source', PaymentEventSource::Webhook)->value('processing_result'))->toBe(PaymentEventResult::Ignored)
        ->and(financeNoticeSent($finance, NotificationCode::ProviderRefundReported))->toBeTrue();
});

it('leaves events unprocessed when the gateway is unreachable and re-dispatches them later', function (): void {
    Queue::fake();
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $payment = openedPayment($owner);
    sendMoyasarWebhook(moyasarEventFor($payment, providerPaymentOf($gateway, $payment)))->assertOk();
    $job = new ProcessGatewayWebhookJob($payment->id);

    $gateway->fail('fetch', ProviderErrorKind::Unavailable);
    expect(fn () => $job->handle(app(ProcessPaymentWebhooks::class)))->toThrow(ProviderCallFailed::class)
        ->and(PaymentEvent::query()->whereNull('processed_at')->where('source', PaymentEventSource::Webhook)->count())->toBe(1);

    $gateway->recover('fetch');
    $this->travel(11)->minutes();
    app(ReconcilePendingPayments::class)->handle();
    Queue::assertPushed(ProcessGatewayWebhookJob::class, 2);

    $job->handle(app(ProcessPaymentWebhooks::class));

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and(PaymentEvent::query()->whereNull('processed_at')->count())->toBe(0);
});

it('queues the processing job unique per payment', function (): void {
    Queue::fake();
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $payment = openedPayment($owner);

    sendMoyasarWebhook(moyasarEventFor($payment, providerPaymentOf($gateway, $payment)))->assertOk();

    Queue::assertPushed(ProcessGatewayWebhookJob::class, static fn ($job): bool => $job->paymentId === $payment->id
        && $job->uniqueId() === 'process-gateway-webhook:'.$payment->id);
});
