<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Files\MalwareScanStatus;
use App\Enums\Files\StoredFilePurpose;
use App\Enums\Finance\CancellationCause;
use App\Enums\Finance\PaymentChannel;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\ProviderErrorKind;
use App\Enums\Notifications\NotificationCode;
use App\Integrations\Fakes\FakeFileScanner;
use App\Models\Audit\Audit;
use App\Models\Entities\Entity;
use App\Models\Files\StoredFile;
use App\Models\Finance\BankAccount;
use App\Models\Finance\BankTransfer;
use App\Models\Finance\CheckoutIntent;
use App\Models\Finance\Payment;
use App\Notifications\Entities\EntityNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

const CHECKOUT_OPTIONS = '/api/v1/payments/checkout-options';

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

it('lists the available channels, the selectable accounts and the unified number for the transfer description', function (): void {
    fakePaymentGateway();
    activatePaymentGateway();
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();
    bankAccount(['iban' => fictionalIban(1), 'is_active' => false, 'deactivated_at' => now()]);

    switchTo($owner)->getJson(CHECKOUT_OPTIONS)
        ->assertOk()
        ->assertJsonPath('data.channels', ['electronic', 'bank_transfer'])
        ->assertJsonCount(1, 'data.bank_accounts')
        ->assertJsonPath('data.bank_accounts.0.id', $account->public_id)
        ->assertJsonPath('data.bank_accounts.0.iban', EXAMPLE_IBAN)
        ->assertJsonPath('data.transfer_note.unified_number', Entity::query()->findOrFail($owner->entity_id)->unified_number);
});

it('hides bank accounts when bank transfer is not offered', function (): void {
    ['owner' => $owner] = transferPayingEntity();
    enablePaymentChannels(PaymentChannel::Electronic);

    switchTo($owner)->getJson(CHECKOUT_OPTIONS)
        ->assertOk()
        ->assertJsonPath('data.channels', [])
        ->assertJsonPath('data.bank_accounts', []);
});

it('keeps checkout options to entity users', function (): void {
    switchTo(financeOfficer())->getJson(CHECKOUT_OPTIONS)->assertForbidden();
    app('auth')->forgetGuards();
    $this->flushSession();
    $this->getJson(CHECKOUT_OPTIONS)->assertUnauthorized();
});

it('creates a bank-transfer request with its receipt under review and tells the entity and the reviewers', function (): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();
    $reviewer = transferReviewer();
    $grantee = staffUser(RoleKey::ContentOfficer);
    $grantee->givePermissionTo(PermissionKey::FinanceTransfersReview->value);
    $admin = systemAdmin();

    $response = submitBankTransfer($owner, $account)
        ->assertCreated()
        ->assertJsonPath('meta.idempotent_replay', false)
        ->assertJsonPath('data.channel', 'bank_transfer')
        ->assertJsonPath('data.status', 'under_review')
        ->assertJsonPath('data.is_test', true)
        ->assertJsonPath('data.expires_at', null)
        ->assertJsonPath('data.checkout', null)
        ->assertJsonPath('data.pricing.total', ACCREDITED_TOTAL)
        ->assertJsonPath('data.bank_transfer.bank_account.iban', EXAMPLE_IBAN)
        ->assertJsonPath('data.bank_transfer.proof.name', 'transfer-receipt.pdf')
        ->assertJsonPath('data.bank_transfer.reviewed_at', null);

    $payment = Payment::query()->where('public_id', $response->json('data.id'))->sole();
    $proof = StoredFile::query()->ofPurpose(StoredFilePurpose::TransferProof)->sole();

    expect($payment->reference)->toMatch('/^PAY-\d{4}-\d{6}$/')
        ->and($payment->provider)->toBeNull()
        ->and($proof->malware_scan_status)->toBe(MalwareScanStatus::Clean)
        ->and($proof->content_class)->toBe('C')
        ->and($proof->attachable)->toBeInstanceOf(BankTransfer::class)
        ->and(Audit::query()->where('event', AuditEvent::BankTransferSubmitted->value)->count())->toBe(1)
        ->and(CheckoutIntent::query()->where('entity_id', $payment->entity_id)->whereNull('converted_payment_id')->exists())->toBeTrue()
        ->and(financeNoticeSent($owner, NotificationCode::BankTransferSubmitted))->toBeTrue()
        ->and(financeNoticeSent($reviewer, NotificationCode::BankTransferSubmitted))->toBeTrue()
        ->and(financeNoticeSent($grantee, NotificationCode::BankTransferSubmitted))->toBeTrue();
    Notification::assertNotSentTo($admin, EntityNotification::class);
    Notification::assertNotSentTo($owner, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::PaymentStarted);
});

it('never shows the reviewer to the entity', function (): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();
    $payment = submittedTransfer($owner, $account);
    $reviewer = transferReviewer();
    rejectTransfer($reviewer, $payment, ['rejection_reason' => 'other', 'note' => TRANSFER_REVIEW_NOTE])->assertOk();

    $body = (string) switchTo($owner)->getJson('/api/v1/payments/'.$payment->public_id)
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.bank_transfer.rejection_reason', 'other')
        ->assertJsonPath('data.bank_transfer.review_note', TRANSFER_REVIEW_NOTE)
        ->getContent();

    expect($body)->not->toContain($reviewer->name)->not->toContain('reviewed_by');
});

it('replays the same request without storing a second receipt', function (): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();
    $key = (string) Str::uuid();

    $first = submitBankTransfer($owner, $account, [], $key)->assertCreated();
    submitBankTransfer($owner, $account, [], $key)
        ->assertCreated()
        ->assertJsonPath('meta.idempotent_replay', true)
        ->assertJsonPath('data.id', $first->json('data.id'));

    expect(StoredFile::query()->ofPurpose(StoredFilePurpose::TransferProof)->count())->toBe(1);
});

it('refuses an idempotency key that was used for a card order', function (): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();
    fakePaymentGateway();
    activatePaymentGateway();
    $key = (string) Str::uuid();
    startPayment($owner, 'accredited', $key)->assertCreated();

    submitBankTransfer($owner, $account, [], $key)->assertUnprocessable()->assertJsonPath('code', 'idempotency_key.reused');
    startPayment($owner, 'accredited', (string) Str::uuid())->assertOk()->assertJsonPath('meta.existing_order', true);
});

it('refuses a card order with a key that was used for a bank transfer', function (): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();
    fakePaymentGateway();
    activatePaymentGateway();
    $key = (string) Str::uuid();
    submitBankTransfer($owner, $account, [], $key)->assertCreated();

    startPayment($owner, 'accredited', $key)->assertUnprocessable()->assertJsonPath('code', 'idempotency_key.reused');
});

it('keeps one open request per service across both channels', function (): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();
    fakePaymentGateway();
    activatePaymentGateway();
    $transfer = submittedTransfer($owner, $account);

    submitBankTransfer($owner, $account)
        ->assertConflict()
        ->assertJsonPath('code', 'payment.open_order_exists')
        ->assertJsonPath('meta.payment_id', $transfer->public_id)
        ->assertJsonPath('meta.channel', 'bank_transfer')
        ->assertJsonPath('meta.status', 'under_review');
    startPayment($owner)->assertConflict()->assertJsonPath('code', 'payment.open_order_exists');

    expect(StoredFile::query()->ofPurpose(StoredFilePurpose::TransferProof)->count())->toBe(1)
        ->and(Storage::disk('local')->allFiles('files/transfer-proof'))->toHaveCount(1);
});

it('refuses a bank transfer while a card order of the same service is open', function (): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();
    fakePaymentGateway();
    activatePaymentGateway();
    $card = startPayment($owner)->assertCreated()->json('data.id');

    submitBankTransfer($owner, $account)
        ->assertConflict()
        ->assertJsonPath('code', 'payment.open_order_exists')
        ->assertJsonPath('meta.payment_id', $card)
        ->assertJsonPath('meta.channel', 'electronic');
});

it('lets the entity submit a new request after a rejection', function (): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();
    $rejected = submittedTransfer($owner, $account);
    rejectTransfer(transferReviewer(), $rejected)->assertOk();

    submitBankTransfer($owner, $account)->assertCreated()->assertJsonPath('data.status', 'under_review');

    expect($rejected->refresh()->status)->toBe(PaymentStatus::Rejected);
});

it('refuses a bank transfer that is not offered or an account that is not selectable', function (Closure $arrange, string $code, string $field): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();
    $target = $arrange($account) ?? $account;

    submitBankTransfer($owner, $target)
        ->assertUnprocessable()
        ->assertJsonPath('code', $code)
        ->assertJsonValidationErrors($field);

    expect(Payment::query()->count())->toBe(0)
        ->and(StoredFile::query()->ofPurpose(StoredFilePurpose::TransferProof)->count())->toBe(0);
})->with([
    'channel disabled' => [static function (): null {
        enablePaymentChannels(PaymentChannel::Electronic);

        return null;
    }, 'payment.channel_not_available', 'channel'],
    'inactive account' => [static fn (): BankAccount => bankAccount(['iban' => fictionalIban(2), 'is_active' => false, 'deactivated_at' => now()]), 'bank_account.not_selectable', 'bank_account_id'],
    'archived account' => [static fn (): BankAccount => bankAccount(['iban' => fictionalIban(3), 'is_active' => false, 'deactivated_at' => now(), 'archived_at' => now()]), 'bank_account.not_selectable', 'bank_account_id'],
]);

it('refuses an account that was deactivated after the entity opened the checkout', function (): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();
    $other = bankAccount(['iban' => fictionalIban(4)]);
    switchTo($owner)->getJson(CHECKOUT_OPTIONS)->assertJsonCount(2, 'data.bank_accounts');
    switchTo(systemAdmin())->postJson('/api/v1/settings/payment/bank-accounts/'.$other->public_id.'/deactivate')->assertOk();

    submitBankTransfer($owner, $other)->assertUnprocessable()->assertJsonPath('code', 'bank_account.not_selectable');
    submitBankTransfer($owner, $account)->assertCreated();
});

it('validates the bank-transfer request', function (array $overrides, string $field): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();

    submitBankTransfer($owner, $account, $overrides)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'missing receipt' => [['proof' => null], 'proof'],
    'missing account' => [['bank_account_id' => null], 'bank_account_id'],
    'account that is not a UUID' => [['bank_account_id' => 'SA03'], 'bank_account_id'],
    'text receipt' => [['proof' => UploadedFile::fake()->createWithContent('receipt.txt', 'transfer done')], 'proof'],
    'oversized receipt' => [['proof' => UploadedFile::fake()->create('receipt.pdf', 21 * 1024, 'application/pdf')], 'proof'],
    'unknown channel' => [['channel' => 'cash'], 'channel'],
]);

it('refuses transfer fields on a card order', function (): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();

    switchTo($owner)->post('/api/v1/payments', ['purpose' => 'accredited', 'channel' => 'electronic', 'bank_account_id' => $account->public_id], ['Accept' => 'application/json', 'Idempotency-Key' => (string) Str::uuid()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('bank_account_id');
});

it('stores no request for an infected, disguised or unscanned receipt', function (Closure $receipt, array $codes): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();

    $code = submitBankTransfer($owner, $account, ['proof' => $receipt()])->assertUnprocessable()->json('code');

    expect($codes)->toContain($code)
        ->and(Payment::query()->count())->toBe(0)
        ->and(StoredFile::query()->ofPurpose(StoredFilePurpose::TransferProof)->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('files/transfer-proof'))->toBe([]);
})->with([
    'infected' => [static fn (): UploadedFile => transferReceipt('receipt.pdf', infectedContents()), ['file.infected']],
    'image renamed as pdf' => [static fn (): UploadedFile => transferReceipt('receipt.pdf', "\x89PNG\r\n\x1a\n0000IHDR"), ['file.content_mismatch', 'validation_failed']],
]);

it('fails closed when the malware scanner is unavailable', function (): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();
    app(FakeFileScanner::class)->makeUnavailable();

    submitBankTransfer($owner, $account)->assertUnprocessable()->assertJsonPath('code', 'file.scan_failed');

    expect(Payment::query()->count())->toBe(0);
});

it('applies the existing payment guards to bank transfers', function (): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();
    setServicePrices(null, null);

    submitBankTransfer($owner, $account)->assertConflict()->assertJsonPath('code', 'payment.price_not_configured');
    submitBankTransfer($owner, $account, ['purpose' => 'leadership'])->assertUnprocessable()->assertJsonPath('code', 'purpose.not_available');

    expect(StoredFile::query()->ofPurpose(StoredFilePurpose::TransferProof)->count())->toBe(0);
});

it('keeps bank-transfer requests to entity users', function (): void {
    ['account' => $account] = transferPayingEntity();

    submitBankTransfer(financeOfficer(), $account)->assertForbidden();
    app('auth')->forgetGuards();
    $this->flushSession();
    $this->post('/api/v1/payments', ['purpose' => 'accredited', 'channel' => 'bank_transfer'], ['Accept' => 'application/json', 'Idempotency-Key' => (string) Str::uuid()])->assertUnauthorized();
});

it('keeps the electronic channel unchanged when no channel is sent and refuses it when it is switched off', function (): void {
    ['owner' => $owner] = payingEntity();
    bankAccount();

    startPayment($owner)->assertCreated()->assertJsonPath('data.channel', 'electronic')->assertJsonPath('data.status', 'created');

    enablePaymentChannels(PaymentChannel::BankTransfer);

    startPayment($owner, 'bag')->assertUnprocessable()->assertJsonPath('code', 'payment.channel_not_available');
});

it('lets the entity download its own transfer receipt and nobody else', function (): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();
    $payment = submittedTransfer($owner, $account);
    $url = '/api/v1/payments/'.$payment->public_id.'/bank-transfer/proof/download';

    $download = (string) switchTo($owner)->postJson($url)->assertOk()->json('data.url');
    $this->get($download)->assertOk();

    switchTo(ownerOf(entityWithOwner()))->postJson($url)->assertNotFound();
});

it('answers 404 for the receipt download of a card order', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $payment = paidPayment($owner, $gateway);

    switchTo($owner)->postJson('/api/v1/payments/'.$payment->public_id.'/bank-transfer/proof/download')->assertNotFound();
});

it('lets the entity cancel an unbound card order to switch to a bank transfer', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $account = bankAccount();
    enablePaymentChannels(PaymentChannel::Electronic, PaymentChannel::BankTransfer);
    $card = openedPayment($owner);

    switchTo($owner)->postJson('/api/v1/payments/'.$card->public_id.'/cancel')
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($gateway->cancelledIntents())->toBe([$card->provider_intent_id])
        ->and(Audit::query()->where('event', AuditEvent::PaymentCancelled->value)->sole()->new_values['cause'] ?? null)->toBe(CancellationCause::CancelledByEntity->value);

    submitBankTransfer($owner, $account)->assertCreated();
});

it('keeps the card order when the gateway cannot cancel it right now', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $card = openedPayment($owner);
    $gateway->fail('cancel', ProviderErrorKind::Unavailable);

    switchTo($owner)->postJson('/api/v1/payments/'.$card->public_id.'/cancel')->assertServiceUnavailable()->assertJsonPath('code', 'provider_unavailable');

    expect($card->refresh()->status)->toBe(PaymentStatus::Created);
});

it('refuses to cancel a bound card order, a bank transfer or another entity order', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $account = bankAccount();
    enablePaymentChannels(PaymentChannel::Electronic, PaymentChannel::BankTransfer);
    $paid = paidPayment($owner, $gateway);
    $bagTransfer = Payment::query()->where('public_id', submitBankTransfer($owner, $account, ['purpose' => 'bag'])->assertCreated()->json('data.id'))->sole();

    switchTo($owner)->postJson('/api/v1/payments/'.$paid->public_id.'/cancel')->assertConflict()->assertJsonPath('code', 'state.transition_illegal');
    $this->postJson('/api/v1/payments/'.$bagTransfer->public_id.'/cancel')->assertConflict()->assertJsonPath('code', 'payment.channel_not_supported');
    switchTo(ownerOf(entityWithOwner()))->postJson('/api/v1/payments/'.$paid->public_id.'/cancel')->assertNotFound();
    switchTo(financeOfficer())->postJson('/api/v1/payments/'.$paid->public_id.'/cancel')->assertForbidden();
});

it('uses live mode for bank transfers only in real production', function (): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();

    expect(submittedTransfer($owner, $account)->mode)->toBe(IntegrationMode::Test);
});

it('counts the bank-transfer request as the open order of its entity in My Payments', function (): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();
    submittedTransfer($owner, $account);

    switchTo($owner)->getJson('/api/v1/payments/mine?filter[status]=under_review')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.channel', 'bank_transfer')
        ->assertJsonPath('meta.status_counts.under_review', 1);
});
