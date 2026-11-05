<?php

declare(strict_types=1);

use App\Actions\Finance\ExpireDuePayments;
use App\Enums\Files\StoredFilePurpose;
use App\Enums\Finance\PaymentChannel;
use App\Enums\Finance\PaymentEventResult;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Identity\RoleKey;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Notifications\NotificationCode;
use App\Models\Finance\BankAccount;
use App\Models\Finance\BankTransfer;
use App\Models\Finance\CheckoutIntent;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentEvent;
use App\Models\Finance\PaymentReceipt;
use App\Notifications\Entities\EntityNotification;
use App\Services\Entities\EntityEraser;
use App\Services\Finance\ReceiptDocumentBuilder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

function liveBankTransfer(BankAccount $account, PaymentStatus $status = PaymentStatus::Paid): Payment
{
    $payment = Payment::factory()->bankTransfer()->create([
        'status' => $status,
        'paid_at' => $status === PaymentStatus::Paid ? now() : null,
        'mode' => IntegrationMode::Live,
    ]);
    $proof = storedTestFile(['purpose' => StoredFilePurpose::TransferProof]);
    $transfer = BankTransfer::query()->create([
        'payment_id' => $payment->id,
        'bank_account_id' => $account->id,
        'proof_file_id' => $proof->id,
        'submitted_by_user_id' => $payment->initiated_by_user_id,
        'submitted_at' => now(),
    ]);
    $proof->attachable()->associate($transfer)->save();

    return $payment->refresh();
}

it('refuses to verify a bank-transfer payment with the gateway', function (): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();
    $payment = submittedTransfer($owner, $account);

    verifyAs($owner, $payment, 'pay_fake_0001')->assertConflict()->assertJsonPath('code', 'payment.channel_not_supported');

    expect($payment->refresh()->status)->toBe(PaymentStatus::UnderReview);
});

it('ignores a gateway payment that names a bank-transfer request and asks finance to return the money', function (): void {
    ['gateway' => $gateway] = payingEntity();
    $account = bankAccount();
    enablePaymentChannels(PaymentChannel::BankTransfer);
    $owner = ownerOf(entityWithOwner());
    $payment = submittedTransfer($owner, $account);
    $finance = financeOfficer();
    $providerId = providerPaymentOf($gateway, $payment);

    sendMoyasarWebhook(moyasarEventFor($payment, $providerId))->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentStatus::UnderReview)
        ->and($payment->provider_payment_id)->toBeNull()
        ->and(PaymentReceipt::query()->count())->toBe(0)
        ->and(PaymentEvent::query()->where('payment_id', $payment->id)->value('processing_result'))->toBe(PaymentEventResult::Rejected)
        ->and(financeNoticeSent($finance, NotificationCode::PaymentRefundRequired))->toBeTrue();
});

it('refuses a manual settlement of a bank-transfer payment', function (): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();
    $payment = submittedTransfer($owner, $account);

    switchTo(superAdmin())->postJson("/api/v1/finance/payments/{$payment->public_id}/manual-settle", [
        'status' => 'paid', 'provider_payment_id' => 'pay_manual_0009', 'reason' => 'تسوية يدوية بعد مراجعة كشف الحساب.',
    ])->assertConflict()->assertJsonPath('code', 'payment.channel_not_supported');
});

it('never expires a transfer under review', function (): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();
    $payment = submittedTransfer($owner, $account);
    Date::setTestNow(now()->addYear());

    expect(app(ExpireDuePayments::class)->handle())->toBe(0)
        ->and($payment->refresh()->status)->toBe(PaymentStatus::UnderReview);
});

it('shows the channel in transactions, filters by it and details the transfer', function (): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();
    $payment = submittedTransfer($owner, $account);
    $reviewer = transferReviewer();
    approveTransfer($reviewer, $payment)->assertOk();
    storedPaidPayment(null, IntegrationMode::Test);
    switchTo(financeOfficer());

    $this->getJson('/api/v1/finance/transactions?filter[mode]=test&filter[channel]=bank_transfer')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.channel', 'bank_transfer')
        ->assertJsonPath('data.0.method', null);
    $this->getJson('/api/v1/finance/transactions?filter[mode]=test&filter[channel]=electronic')->assertJsonPath('meta.pagination.total', 1);
    $this->getJson('/api/v1/finance/transactions?filter[channel]=cash')->assertUnprocessable();
    $this->getJson("/api/v1/finance/transactions/{$payment->public_id}")
        ->assertOk()
        ->assertJsonPath('data.expires_at', null)
        ->assertJsonPath('data.bank_transfer.bank_account.iban', EXAMPLE_IBAN)
        ->assertJsonPath('data.bank_transfer.reviewed_by.id', $reviewer->id);
});

it('exports the channel and the destination bank and counts transfers in the summary', function (): void {
    $account = bankAccount();
    $paid = liveBankTransfer($account);
    liveBankTransfer(bankAccount(['iban' => fictionalIban(1)]), PaymentStatus::UnderReview);
    issueReceiptFor($paid);
    storedPaidPayment(null, IntegrationMode::Live);
    switchTo(financeOfficer());

    $csv = $this->post('/api/v1/finance/transactions/export', [], ['Accept' => 'application/json'])->assertOk()->streamedContent();

    expect($csv)->toContain('قناة الدفع')->toContain('تحويل بنكي')->toContain('بنك المثال (مثال)')->toContain('دفع إلكتروني');

    $summary = $this->getJson('/api/v1/finance/summary')->assertOk()
        ->assertJsonPath('data.transactions_count', 3)
        ->assertJsonPath('data.incomplete_count', 1)
        ->json('data.by_channel');

    expect(collect($summary)->keyBy('channel')->map(static fn (array $row): int => $row['transactions_count'])->all())->toBe(['electronic' => 1, 'bank_transfer' => 2]);
});

it('keeps an entity whose transfer is under review out of the abandoned carts', function (): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();
    submittedTransfer($owner, $account);
    CheckoutIntent::query()->update(['last_seen_at' => now()->subHours(25)]);

    switchTo(systemAdmin())->getJson('/api/v1/messaging/abandoned-carts')->assertOk()->assertJsonPath('meta.pagination.total', 0);
});

it('converts the checkout intent when the transfer is approved', function (): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();
    $payment = submittedTransfer($owner, $account);

    approveTransfer(transferReviewer(), $payment)->assertOk();

    expect(CheckoutIntent::query()->where('converted_payment_id', $payment->id)->exists())->toBeTrue();
});

it('shows the entity a pending transfer, then a rejected one until a new request exists', function (): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();
    $payment = submittedTransfer($owner, $account);

    $items = actionItems(boardOf($owner));
    expect($items->get('bank_transfer_under_review'))->toMatchArray(['count' => 1, 'severity' => 'info', 'link' => '/payments'])
        ->and($items->get('bank_transfer_under_review')['context']['payment_id'] ?? null)->toBe($payment->public_id)
        ->and($items->has('complete_payment'))->toBeFalse()
        ->and($items->has('bank_transfer_rejected'))->toBeFalse();

    rejectTransfer(transferReviewer(), $payment)->assertOk();

    $items = actionItems(boardOf($owner));
    expect($items->get('bank_transfer_rejected'))->toMatchArray(['count' => 1, 'severity' => 'danger'])
        ->and($items->has('bank_transfer_under_review'))->toBeFalse();

    submittedTransfer($owner, $account, SAMPLE_PDF.'%second');

    expect(actionItems(boardOf($owner))->has('bank_transfer_rejected'))->toBeFalse();
});

it('shows reviewers how many transfers await approval and nobody else', function (): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();
    submittedTransfer($owner, $account);

    expect(actionItems(boardOf(transferReviewer()))->get('review_bank_transfers'))->toMatchArray(['count' => 1, 'severity' => 'warning', 'link' => '/finance/transfers'])
        ->and(actionItems(boardOf(systemAdmin()))->has('review_bank_transfers'))->toBeFalse()
        ->and(actionItems(boardOf(staffUser(RoleKey::ContentOfficer)))->has('review_bank_transfers'))->toBeFalse();
});

it('lists a transfer under review among the open requests of an entity to delete', function (): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();
    $payment = submittedTransfer($owner, $account);
    switchTo(systemAdmin());

    $this->getJson("/api/v1/entities/{$payment->entity_id}/deletion-impact")
        ->assertOk()
        ->assertJsonPath('data.open_requests_count', 1)
        ->assertJsonPath('data.open_requests.0.number', $payment->reference)
        ->assertJsonPath('data.open_requests.0.status', 'under_review');
});

it('cancels a transfer under review on permanent erasure, keeps its records and asks finance to return the money', function (): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();
    $payment = submittedTransfer($owner, $account);
    $finance = financeOfficer();
    $entity = $payment->entity;
    $entity->delete();

    app(EntityEraser::class)->erase($entity->refresh());

    expect($payment->refresh()->status)->toBe(PaymentStatus::Cancelled)
        ->and($payment->bankTransfer)->not->toBeNull()
        ->and($payment->bankTransfer?->proofFile)->not->toBeNull();
    Notification::assertSentTo($finance, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::PaymentRefundRequired
        && str_contains($notification->notice->title->en, 'Bank transfer'));
});

it('prints the bank transfer and the destination bank on the receipt', function (): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();
    $payment = submittedTransfer($owner, $account);
    approveTransfer(transferReviewer(), $payment)->assertOk();

    $document = app(ReceiptDocumentBuilder::class)->for(PaymentReceipt::query()->where('payment_id', $payment->id)->sole());

    expect($document->data['method_label'] ?? null)->toBe(['ar' => 'تحويل بنكي', 'en' => 'Bank transfer'])
        ->and($document->data['bank_name'] ?? null)->toBe(['ar' => 'بنك المثال (مثال)', 'en' => 'Example Bank (example)'])
        ->and($document->data['card_last4'] ?? null)->toBeNull();
});
