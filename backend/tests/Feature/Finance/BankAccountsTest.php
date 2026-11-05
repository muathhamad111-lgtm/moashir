<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Finance\PaymentChannel;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Models\Audit\Audit;
use App\Models\Finance\BankAccount;
use App\Notifications\Entities\EntityNotification;
use App\Notifications\Identity\SecurityAlertNotification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

const BANK_ACCOUNTS = '/api/v1/settings/payment/bank-accounts';

const BANK_ACCOUNT_REASON = 'استبدال الحساب البنكي بطلب من الإدارة المالية.';

beforeEach(function (): void {
    Notification::fake();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function bankAccountPayload(array $overrides = []): array
{
    return [
        'bank_name' => ['ar' => 'بنك المثال (مثال)', 'en' => 'Example Bank (example)'],
        'account_holder_name' => ['ar' => 'منصة مؤشر (مثال)', 'en' => 'Moashir Platform (example)'],
        'iban' => 'sa03 8000 0000 6080 1016 7519',
        'payer_note' => ['ar' => 'اكتب الرقم الوطني الموحد للجهة في وصف التحويل.', 'en' => 'Write the unified national number in the transfer description.'],
        ...$overrides,
    ];
}

function bankAccountUrl(BankAccount $account, string $action = ''): string
{
    return BANK_ACCOUNTS.'/'.$account->public_id.($action === '' ? '' : '/'.$action);
}

function bankAccountNoticeSentTo(object $notifiable): bool
{
    return financeNoticeSent($notifiable, NotificationCode::BankAccountsChanged);
}

it('adds an active account with a normalized IBAN, audits it and alerts finance and administrators', function (): void {
    $admin = systemAdmin();
    $otherAdmin = systemAdmin();
    $finance = financeOfficer();

    switchTo($admin)->postJson(BANK_ACCOUNTS, bankAccountPayload())
        ->assertCreated()
        ->assertJsonPath('data.iban', EXAMPLE_IBAN)
        ->assertJsonPath('data.bank_name.en', 'Example Bank (example)')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.is_used', false)
        ->assertJsonPath('data.pending_transfers_count', 0)
        ->assertJsonPath('data.display_order', 1)
        ->assertJsonPath('data.created_by', ['id' => $admin->id, 'name' => $admin->name]);

    $audit = Audit::query()->where('event', AuditEvent::BankAccountCreated->value)->sole();

    expect($audit->new_values['iban'] ?? null)->toBe(EXAMPLE_IBAN)
        ->and(bankAccountNoticeSentTo($finance))->toBeTrue()
        ->and(bankAccountNoticeSentTo($otherAdmin))->toBeTrue();
    Notification::assertNotSentTo($admin, EntityNotification::class);
});

it('never puts the IBAN in the bank-account alert', function (): void {
    $finance = financeOfficer();

    switchTo(systemAdmin())->postJson(BANK_ACCOUNTS, bankAccountPayload())->assertCreated();

    Notification::assertSentTo($finance, EntityNotification::class, static fn (EntityNotification $notification): bool => ! str_contains($notification->notice->body->ar.$notification->notice->body->en, EXAMPLE_IBAN)
        && str_contains($notification->notice->body->en, '7519'));
});

it('refuses an invalid or duplicate IBAN', function (string $iban, string $message): void {
    bankAccount(['iban' => fictionalIban(1)]);

    switchTo(systemAdmin())->postJson(BANK_ACCOUNTS, bankAccountPayload(['iban' => $iban]))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonPath('errors.iban.0', __($message));
})->with([
    'wrong length' => ['SA03800000006080101675', 'validation.custom.iban.format'],
    'not Saudi' => ['GB82WEST12345698765432', 'validation.custom.iban.format'],
    'wrong check digits' => ['SA0480000000608010167519', 'validation.custom.iban.checksum'],
    'already registered' => [fictionalIban(1), 'validation.custom.iban.unique'],
]);

it('refuses an IBAN already used by an archived account', function (): void {
    $archived = bankAccount(['is_active' => false, 'deactivated_at' => now(), 'archived_at' => now()]);

    switchTo(systemAdmin())->postJson(BANK_ACCOUNTS, bankAccountPayload(['iban' => $archived->iban]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.iban.0', __('validation.custom.iban.unique'));
});

it('validates the payee details', function (array $overrides, string $field): void {
    switchTo(systemAdmin())->postJson(BANK_ACCOUNTS, bankAccountPayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'missing English bank name' => [['bank_name' => ['ar' => 'بنك المثال']], 'bank_name.en'],
    'markup in the holder name' => [['account_holder_name' => ['ar' => '<b>منصة</b>', 'en' => 'Moashir']], 'account_holder_name.ar'],
    'short account number' => [['account_number' => '12'], 'account_number'],
    'unknown note language' => [['payer_note' => ['fr' => 'note']], 'payer_note'],
]);

it('stops at the maximum number of active accounts', function (): void {
    Config::set('moashir.finance.bank_transfer.max_active_accounts', 1);
    bankAccount(['iban' => fictionalIban(2)]);

    switchTo(systemAdmin())->postJson(BANK_ACCOUNTS, bankAccountPayload())
        ->assertUnprocessable()
        ->assertJsonPath('code', 'bank_account.limit_reached')
        ->assertJsonPath('meta.max_active_accounts', 1);
});

it('lists the accounts in display order without archived ones and filters by state', function (): void {
    $second = bankAccount(['iban' => fictionalIban(2), 'display_order' => 2]);
    $first = bankAccount(['iban' => fictionalIban(1), 'display_order' => 1, 'is_active' => false, 'deactivated_at' => now()]);
    bankAccount(['iban' => fictionalIban(3), 'display_order' => 3, 'is_active' => false, 'deactivated_at' => now(), 'archived_at' => now()]);

    switchTo(systemAdmin())->getJson(BANK_ACCOUNTS)
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 2)
        ->assertJsonPath('data.0.id', $first->public_id)
        ->assertJsonPath('data.1.id', $second->public_id);

    $this->getJson(BANK_ACCOUNTS.'?filter[active]=1')->assertOk()->assertJsonPath('meta.pagination.total', 1)->assertJsonPath('data.0.id', $second->public_id);
    $this->getJson(BANK_ACCOUNTS.'?filter[colour]=red')->assertUnprocessable();
});

it('edits only the note to payers and refuses payee changes', function (): void {
    $account = bankAccount();
    switchTo(systemAdmin());

    $this->patchJson(bankAccountUrl($account), ['payer_note' => ['ar' => 'ملاحظة جديدة', 'en' => 'New note']])
        ->assertOk()
        ->assertJsonPath('data.payer_note.en', 'New note');
    $this->patchJson(bankAccountUrl($account), ['payer_note' => null])->assertOk()->assertJsonPath('data.payer_note', null);

    foreach (['iban' => fictionalIban(4), 'bank_name' => ['ar' => 'بنك آخر', 'en' => 'Other Bank'], 'account_number' => '12345678'] as $field => $value) {
        $this->patchJson(bankAccountUrl($account), ['payer_note' => null, $field => $value])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'bank_account.payee_fields_immutable');
    }

    expect($account->refresh()->iban)->toBe(EXAMPLE_IBAN)
        ->and(Audit::query()->where('event', AuditEvent::BankAccountUpdated->value)->count())->toBe(2);
});

it('blocks payee changes in the database too', function (string $column, mixed $value): void {
    $account = bankAccount();

    expect(fn () => DB::table('bank_accounts')->where('id', $account->id)->update([$column => $value]))->toThrow(QueryException::class);
})->with([
    'iban' => ['iban', fictionalIban(5)],
    'bank name' => ['bank_name', json_encode(['ar' => 'بنك آخر', 'en' => 'Other Bank'])],
    'account holder' => ['account_holder_name', json_encode(['ar' => 'جهة أخرى', 'en' => 'Other'])],
    'account number' => ['account_number', '99999999'],
]);

it('deactivates and reactivates an account with an alert each time', function (): void {
    bankAccount(['iban' => fictionalIban(6)]);
    $account = bankAccount();
    $finance = financeOfficer();
    switchTo(systemAdmin());

    $this->postJson(bankAccountUrl($account, 'deactivate'))->assertOk()->assertJsonPath('data.is_active', false);
    $this->postJson(bankAccountUrl($account, 'deactivate'))->assertConflict()->assertJsonPath('code', 'state.transition_illegal');
    $this->postJson(bankAccountUrl($account, 'activate'))->assertOk()->assertJsonPath('data.is_active', true)->assertJsonPath('data.deactivated_at', null);
    $this->postJson(bankAccountUrl($account, 'activate'))->assertConflict();

    expect(Audit::query()->whereIn('event', [AuditEvent::BankAccountDeactivated->value, AuditEvent::BankAccountActivated->value])->count())->toBe(2);
    Notification::assertSentToTimes($finance, EntityNotification::class, 2);
});

it('keeps the last active account while bank transfer is enabled', function (): void {
    $account = bankAccount();
    enablePaymentChannels(PaymentChannel::BankTransfer);
    switchTo(systemAdmin());

    $this->postJson(bankAccountUrl($account, 'deactivate'))->assertConflict()->assertJsonPath('code', 'bank_account.last_active_account');
    $this->deleteJson(bankAccountUrl($account))->assertConflict()->assertJsonPath('code', 'bank_account.last_active_account');

    enablePaymentChannels(PaymentChannel::Electronic);

    $this->postJson(bankAccountUrl($account, 'deactivate'))->assertOk();
});

it('refuses to activate beyond the maximum number of active accounts', function (): void {
    Config::set('moashir.finance.bank_transfer.max_active_accounts', 1);
    bankAccount(['iban' => fictionalIban(7)]);
    $inactive = bankAccount(['is_active' => false, 'deactivated_at' => now()]);

    switchTo(systemAdmin())->postJson(bankAccountUrl($inactive, 'activate'))->assertUnprocessable()->assertJsonPath('code', 'bank_account.limit_reached');
});

it('blocks deactivating or deleting an account while transfers to it await approval, and lists them', function (): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();
    bankAccount(['iban' => fictionalIban(8)]);
    $payment = submittedTransfer($owner, $account);
    switchTo(systemAdmin());

    foreach ([fn () => $this->postJson(bankAccountUrl($account, 'deactivate')), fn () => $this->deleteJson(bankAccountUrl($account))] as $attempt) {
        $attempt()
            ->assertConflict()
            ->assertJsonPath('code', 'bank_account.pending_transfers')
            ->assertJsonPath('meta.transfers.0.payment_id', $payment->public_id)
            ->assertJsonPath('meta.transfers.0.reference', $payment->reference)
            ->assertJsonPath('meta.transfers.0.total', ACCREDITED_TOTAL)
            ->assertJsonPath('meta.transfers.0.entity_name', $payment->entity_name->toArray());
    }

    $listed = collect($this->getJson(BANK_ACCOUNTS)->assertOk()->json('data'))->keyBy('id');

    expect($listed->get($account->public_id)['pending_transfers_count'] ?? null)->toBe(1)
        ->and($listed->get($account->public_id)['is_used'] ?? null)->toBeTrue();

    approveTransfer(transferReviewer(), $payment)->assertOk();

    switchTo(systemAdmin())->postJson(bankAccountUrl($account, 'deactivate'))->assertOk();
});

it('deletes a never-used account and archives a used one', function (): void {
    ['owner' => $owner, 'account' => $used] = transferPayingEntity();
    $unused = bankAccount(['iban' => fictionalIban(9)]);
    $payment = submittedTransfer($owner, $used);
    approveTransfer(transferReviewer(), $payment)->assertOk();
    switchTo(systemAdmin());

    $this->deleteJson(bankAccountUrl($unused))->assertNoContent();
    $this->deleteJson(bankAccountUrl($used))->assertConflict()->assertJsonPath('code', 'bank_account.last_active_account');

    enablePaymentChannels(PaymentChannel::Electronic);

    $this->deleteJson(bankAccountUrl($used))->assertNoContent();
    $this->getJson(BANK_ACCOUNTS)->assertJsonPath('meta.pagination.total', 0);
    $this->postJson(bankAccountUrl($used, 'activate'))->assertNotFound();

    expect(BankAccount::query()->whereKey($unused->id)->exists())->toBeFalse()
        ->and($used->refresh()->archived_at)->not->toBeNull()
        ->and($used->is_active)->toBeFalse()
        ->and(Audit::query()->where('event', AuditEvent::BankAccountDeleted->value)->count())->toBe(1)
        ->and(Audit::query()->where('event', AuditEvent::BankAccountArchived->value)->count())->toBe(1)
        ->and($payment->refresh()->receipt?->snapshot['bank_name'] ?? null)->toBe(['ar' => 'بنك المثال (مثال)', 'en' => 'Example Bank (example)']);
});

it('never un-archives an account in the database', function (): void {
    $account = bankAccount(['is_active' => false, 'deactivated_at' => now(), 'archived_at' => now()]);

    expect(fn () => DB::table('bank_accounts')->where('id', $account->id)->update(['archived_at' => null]))->toThrow(QueryException::class);
});

it('reorders every listed account and refuses a partial order', function (): void {
    $first = bankAccount(['iban' => fictionalIban(10), 'display_order' => 1]);
    $second = bankAccount(['iban' => fictionalIban(11), 'display_order' => 2]);
    switchTo(systemAdmin());

    $this->putJson(BANK_ACCOUNTS.'/order', ['ordered_ids' => [$second->public_id, $first->public_id]])
        ->assertOk()
        ->assertJsonPath('data.0.id', $second->public_id)
        ->assertJsonPath('data.0.display_order', 1)
        ->assertJsonPath('data.1.display_order', 2);

    $this->putJson(BANK_ACCOUNTS.'/order', ['ordered_ids' => [$second->public_id]])->assertUnprocessable()->assertJsonValidationErrors('ordered_ids');
    $this->putJson(BANK_ACCOUNTS.'/order', ['ordered_ids' => ['not-a-uuid']])->assertUnprocessable();

    expect(Audit::query()->where('event', AuditEvent::BankAccountsReordered->value)->count())->toBe(2);
});

it('keeps bank accounts away from everyone but the System Administrator', function (RoleKey $role): void {
    $account = bankAccount();

    switchTo(staffUser($role))->getJson(BANK_ACCOUNTS)->assertForbidden();
    $this->postJson(BANK_ACCOUNTS, bankAccountPayload())->assertForbidden();
    $this->patchJson(bankAccountUrl($account), ['payer_note' => null])->assertForbidden();
    $this->postJson(bankAccountUrl($account, 'deactivate'))->assertForbidden();
    $this->deleteJson(bankAccountUrl($account))->assertForbidden();
})->with([RoleKey::FinanceOfficer, RoleKey::ContentOfficer]);

it('refuses guests and answers 404 for an unknown account', function (): void {
    $this->getJson(BANK_ACCOUNTS)->assertUnauthorized();

    switchTo(systemAdmin())->postJson(BANK_ACCOUNTS.'/'.fake()->uuid().'/deactivate')->assertNotFound();
});

it('requires a reason from the Super Admin for account changes', function (): void {
    $superAdmin = superAdmin();
    switchTo($superAdmin);

    $this->postJson(BANK_ACCOUNTS, bankAccountPayload())->assertUnprocessable()->assertJsonPath('code', 'reason_required');
    $this->postJson(BANK_ACCOUNTS, bankAccountPayload(['reason' => BANK_ACCOUNT_REASON]))->assertCreated()->assertJsonPath('data.created_by.name', null);

    expect(Audit::query()->where('event', AuditEvent::BankAccountCreated->value)->sole()->reason)->toBe(BANK_ACCOUNT_REASON);
    Notification::assertSentTo($superAdmin, SecurityAlertNotification::class);
});
