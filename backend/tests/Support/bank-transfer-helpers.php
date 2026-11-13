<?php

declare(strict_types=1);

use App\Enums\Finance\PaymentChannel;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Settings\SettingKey;
use App\Models\Finance\BankAccount;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

const EXAMPLE_IBAN = 'SA0380000000608010167519';

const FICTIONAL_BANK_CODE = '00';

const TRANSFER_REVIEW_NOTE = 'لم يظهر المبلغ في كشف الحساب حتى تاريخ اليوم.';

function fictionalIban(int $serial): string
{
    $basic = FICTIONAL_BANK_CODE.str_pad((string) $serial, 18, '0', STR_PAD_LEFT);
    $remainder = 0;

    foreach (str_split($basic.'2810'.'00') as $digit) {
        $remainder = ($remainder * 10 + (int) $digit) % 97;
    }

    return 'SA'.str_pad((string) (98 - $remainder), 2, '0', STR_PAD_LEFT).$basic;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function bankAccount(array $attributes = []): BankAccount
{
    return BankAccount::factory()->create(['created_by_user_id' => systemAdmin()->id, ...$attributes])->refresh();
}

/**
 * @param  list<PaymentChannel>  $channels
 */
function enablePaymentChannels(PaymentChannel ...$channels): void
{
    setIntegrationSetting(SettingKey::PaymentChannels, array_map(static fn (PaymentChannel $channel): string => $channel->value, $channels));
}

/**
 * @return array{owner: User, account: BankAccount}
 */
function transferPayingEntity(): array
{
    setServicePrices();
    $account = bankAccount();
    enablePaymentChannels(PaymentChannel::Electronic, PaymentChannel::BankTransfer);

    return ['owner' => ownerOf(entityWithOwner()), 'account' => $account];
}

function transferReceipt(string $name = 'transfer-receipt.pdf', string $contents = SAMPLE_PDF): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $contents);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function submitBankTransfer(User $owner, BankAccount $account, array $overrides = [], ?string $key = null): TestResponse
{
    return switchTo($owner)->post('/api/v1/payments', [
        'purpose' => 'accredited',
        'channel' => 'bank_transfer',
        'bank_account_id' => $account->public_id,
        'proof' => transferReceipt(),
        ...$overrides,
    ], ['Accept' => 'application/json', 'Idempotency-Key' => $key ?? (string) Str::uuid()]);
}

function submittedTransfer(User $owner, BankAccount $account, string $contents = SAMPLE_PDF): Payment
{
    $id = submitBankTransfer($owner, $account, ['proof' => transferReceipt('transfer-receipt.pdf', $contents)])->assertCreated()->json('data.id');

    return Payment::query()->where('public_id', $id)->firstOrFail();
}

function transferReviewer(): User
{
    return financeOfficer();
}

function bankTransferUrl(Payment $payment, string $action = ''): string
{
    return '/api/v1/finance/bank-transfers/'.$payment->public_id.($action === '' ? '' : '/'.$action);
}

function approveTransfer(User $reviewer, Payment $payment, array $payload = []): TestResponse
{
    return switchTo($reviewer)->postJson(bankTransferUrl($payment, 'approve'), $payload);
}

/**
 * @param  array<string, mixed>  $payload
 */
function rejectTransfer(User $reviewer, Payment $payment, array $payload = ['rejection_reason' => 'not_received']): TestResponse
{
    return switchTo($reviewer)->postJson(bankTransferUrl($payment, 'reject'), $payload);
}

function underReviewCount(): int
{
    return Payment::query()->where('status', PaymentStatus::UnderReview)->count();
}
