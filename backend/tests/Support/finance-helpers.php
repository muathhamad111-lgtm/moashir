<?php

declare(strict_types=1);

use App\Contracts\Integrations\PaymentGateway;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Identity\RoleKey;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Notifications\NotificationCode;
use App\Enums\Settings\SettingKey;
use App\Integrations\Fakes\FakePaymentGateway;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentReceipt;
use App\Models\Identity\User;
use App\Notifications\Entities\EntityNotification;
use App\Services\Finance\ReceiptIssuer;
use App\Support\ValueObjects\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

const ACCREDITED_PRICE = '1000.00';

const ACCREDITED_TOTAL = '1150.00';

const BAG_PRICE = '2500.00';

const BAG_TOTAL = '2875.00';

const REFUND_REASON = 'أعيد المبلغ بتحويل بنكي بعد طلب الجهة إلغاء الخدمة قبل البدء.';

function fakePaymentGateway(): FakePaymentGateway
{
    $gateway = new FakePaymentGateway;
    app()->instance(PaymentGateway::class, $gateway);

    return $gateway;
}

function activatePaymentGateway(IntegrationMode $mode = IntegrationMode::Test): void
{
    configureMoyasar($mode, [...passedTestState(), 'is_active' => true]);
    setIntegrationSetting(SettingKey::PaymentMode, $mode->value);
}

function setServicePrices(?string $accredited = ACCREDITED_PRICE, ?string $bag = BAG_PRICE): void
{
    setIntegrationSetting(SettingKey::AccreditedPrice, $accredited);
    setIntegrationSetting(SettingKey::BagPrice, $bag);
}

/**
 * @return array{gateway: FakePaymentGateway, owner: User, entity: Entity}
 */
function payingEntity(IntegrationMode $mode = IntegrationMode::Test): array
{
    $gateway = fakePaymentGateway();
    activatePaymentGateway($mode);
    setServicePrices();
    $entity = entityWithOwner();

    return ['gateway' => $gateway, 'owner' => ownerOf($entity), 'entity' => $entity];
}

function startPayment(User $owner, string $purpose = 'accredited', ?string $key = null): TestResponse
{
    return switchTo($owner)->postJson('/api/v1/payments', ['purpose' => $purpose], ['Idempotency-Key' => $key ?? (string) Str::uuid()]);
}

function openedPayment(User $owner, string $purpose = 'accredited'): Payment
{
    $id = startPayment($owner, $purpose)->assertCreated()->json('data.id');

    return Payment::query()->where('public_id', $id)->firstOrFail();
}

function providerPaymentOf(FakePaymentGateway $gateway, Payment $payment, string $status = 'paid', ?string $amount = null, ?string $responseCode = null, ?string $id = null): string
{
    $providerId = $id ?? (string) Str::uuid();
    $gateway->recordPayment($providerId, $status, new Money($amount ?? $payment->total), ['moashir_payment_id' => $payment->public_id], $responseCode);

    return $providerId;
}

function verifyAs(User $owner, Payment $payment, string $providerPaymentId): TestResponse
{
    return switchTo($owner)->postJson("/api/v1/payments/{$payment->public_id}/verify", ['provider_payment_id' => $providerPaymentId]);
}

function paidPayment(User $owner, FakePaymentGateway $gateway, string $purpose = 'accredited'): Payment
{
    $payment = openedPayment($owner, $purpose);
    verifyAs($owner, $payment, providerPaymentOf($gateway, $payment))->assertOk()->assertJsonPath('data.status', 'paid');

    return $payment->refresh();
}

function storedPaidPayment(?Entity $entity = null, IntegrationMode $mode = IntegrationMode::Live, PaymentPurpose $purpose = PaymentPurpose::Accredited): Payment
{
    $factory = Payment::factory()->paid()->state(['mode' => $mode, 'purpose' => $purpose]);
    $payment = ($entity instanceof Entity ? $factory->forEntity($entity) : $factory)->create();

    issueReceiptFor($payment);

    return $payment->refresh();
}

function issueReceiptFor(Payment $payment): PaymentReceipt
{
    return DB::transaction(static fn (): PaymentReceipt => app(ReceiptIssuer::class)->issue($payment->load('entity')));
}

/**
 * @param  array<string, mixed>  $overrides
 * @param  array<string, mixed>  $data
 */
function moyasarEventFor(?Payment $payment, string $providerPaymentId, string $type = 'payment_paid', array $data = [], array $overrides = []): string
{
    return (string) json_encode([
        'id' => 'evt-'.Str::uuid()->toString(),
        'type' => $type,
        'created_at' => '2026-11-01T08:00:00Z',
        'secret_token' => FAKE_WEBHOOK_SECRET,
        'account_name' => 'Moashir',
        'live' => $payment?->mode === IntegrationMode::Live,
        'data' => [
            'id' => $providerPaymentId,
            'status' => str_replace('payment_', '', $type),
            'amount' => $payment instanceof Payment ? $payment->totalMoney()->inMinorUnits() : 115000,
            'currency' => 'SAR',
            'ip' => '62.149.100.20',
            'metadata' => $payment instanceof Payment ? ['moashir_payment_id' => $payment->public_id] : [],
            'source' => ['type' => 'creditcard', 'company' => 'mada', 'name' => 'Salman Al-Otaibi', 'number' => '4201-32XX-XXXX-1111'],
            ...$data,
        ],
        ...$overrides,
    ]);
}

function sendMoyasarWebhook(string $payload): TestResponse
{
    return test()->call('POST', '/api/v1/webhooks/moyasar', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => '*/*'], $payload);
}

function financeNoticeSent(object $notifiable, NotificationCode $code): bool
{
    $found = false;

    Notification::assertSentTo($notifiable, EntityNotification::class, static function (EntityNotification $notification) use ($code, &$found): bool {
        $found = $found || $notification->notice->code === $code;

        return true;
    });

    return $found;
}

function financeOfficer(): User
{
    return staffUser(RoleKey::FinanceOfficer);
}

function bankTransferReceipt(string $name = 'bank-transfer.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n%Moashir bank transfer receipt\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function refundPayload(Payment $payment, array $overrides = []): array
{
    return [
        'amount' => $payment->total,
        'refunded_on' => $payment->paid_at?->copy()->setTimezone('Asia/Riyadh')->toDateString(),
        'reason' => REFUND_REASON,
        'transfer_reference' => 'TRF-2026-55871',
        'evidence' => bankTransferReceipt(),
        ...$overrides,
    ];
}
