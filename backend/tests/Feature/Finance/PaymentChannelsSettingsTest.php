<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Finance\PaymentChannel;
use App\Enums\Identity\RoleKey;
use App\Enums\Settings\SettingKey;
use App\Models\Audit\Audit;
use App\Notifications\Identity\SecurityAlertNotification;
use App\Services\Settings\SettingsStore;
use Illuminate\Support\Facades\Notification;

const PAYMENT_CHANNELS = '/api/v1/settings/payment/channels';

const CHANNELS_REASON = 'تفعيل التحويل البنكي بطلب من الإدارة المالية.';

beforeEach(function (): void {
    Notification::fake();
});

it('shows the electronic channel by default with the readiness of each channel', function (): void {
    switchTo(systemAdmin())->getJson(PAYMENT_CHANNELS)
        ->assertOk()
        ->assertJsonPath('data.channels', ['electronic'])
        ->assertJsonPath('data.available_channels', [])
        ->assertJsonPath('data.gateway_ready', false)
        ->assertJsonPath('data.active_bank_accounts_count', 0)
        ->assertJsonPath('data.reviewers_count', 0);
});

it('enables both channels when the gateway is ready and an active account exists, and counts the reviewers', function (): void {
    activatePaymentGateway();
    bankAccount();
    financeOfficer();
    $admin = systemAdmin();

    switchTo($admin)->putJson(PAYMENT_CHANNELS, ['channels' => ['bank_transfer', 'electronic']])
        ->assertOk()
        ->assertJsonPath('data.channels', ['electronic', 'bank_transfer'])
        ->assertJsonPath('data.available_channels', ['electronic', 'bank_transfer'])
        ->assertJsonPath('data.gateway_ready', true)
        ->assertJsonPath('data.active_bank_accounts_count', 1)
        ->assertJsonPath('data.reviewers_count', 1);

    $audit = Audit::query()->where('event', AuditEvent::PaymentChannelsUpdated->value)->sole();

    expect($audit->new_values)->toBe([SettingKey::PaymentChannels->value => ['electronic', 'bank_transfer']])
        ->and($audit->user_id)->toBe($admin->id);
});

it('lets bank transfer be the only channel without a payment gateway', function (): void {
    bankAccount();

    switchTo(systemAdmin())->putJson(PAYMENT_CHANNELS, ['channels' => ['bank_transfer']])
        ->assertOk()
        ->assertJsonPath('data.available_channels', ['bank_transfer']);
});

it('refuses a channel that is not ready', function (array $channels, string $code): void {
    switchTo(systemAdmin())->putJson(PAYMENT_CHANNELS, ['channels' => $channels])
        ->assertConflict()
        ->assertJsonPath('code', $code);

    expect(app(SettingsStore::class)->get(SettingKey::PaymentChannels))->toBe(['electronic']);
})->with([
    'electronic without a configured gateway' => [['electronic'], 'payment.gateway_not_ready'],
    'bank transfer without an active account' => [['bank_transfer'], 'payment.bank_account_required'],
]);

it('refuses a bank transfer channel when every account is inactive', function (): void {
    bankAccount(['is_active' => false, 'deactivated_at' => now()]);

    switchTo(systemAdmin())->putJson(PAYMENT_CHANNELS, ['channels' => ['bank_transfer']])
        ->assertConflict()
        ->assertJsonPath('code', 'payment.bank_account_required');
});

it('validates the channel list', function (mixed $channels): void {
    switchTo(systemAdmin())->putJson(PAYMENT_CHANNELS, ['channels' => $channels])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed');
})->with([
    'empty' => [[]],
    'duplicate' => [['bank_transfer', 'bank_transfer']],
    'unknown' => [['cash']],
    'not a list' => [['a' => 'electronic']],
    'missing' => [null],
]);

it('keeps the channel settings away from everyone but the System Administrator', function (RoleKey $role): void {
    switchTo(staffUser($role))->getJson(PAYMENT_CHANNELS)->assertForbidden();
    $this->putJson(PAYMENT_CHANNELS, ['channels' => ['electronic']])->assertForbidden();
})->with([RoleKey::FinanceOfficer, RoleKey::ContentOfficer, RoleKey::RequestFollower]);

it('refuses entities and guests', function (): void {
    switchTo(ownerOf(entityWithOwner()))->getJson(PAYMENT_CHANNELS)->assertForbidden();
    app('auth')->forgetGuards();
    $this->flushSession();

    $this->getJson(PAYMENT_CHANNELS)->assertUnauthorized();
});

it('requires a reason from the Super Admin and alerts on the reasoned change', function (): void {
    bankAccount();
    $superAdmin = superAdmin();
    switchTo($superAdmin);

    $this->putJson(PAYMENT_CHANNELS, ['channels' => ['bank_transfer']])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'reason_required');
    Notification::assertNothingSent();

    $this->putJson(PAYMENT_CHANNELS, ['channels' => ['bank_transfer'], 'reason' => CHANNELS_REASON])->assertOk();

    expect(Audit::query()->where('event', AuditEvent::PaymentChannelsUpdated->value)->sole()->reason)->toBe(CHANNELS_REASON);
    Notification::assertSentTo($superAdmin, SecurityAlertNotification::class);
});

it('reads only enabled channels from storage', function (): void {
    setIntegrationSetting(SettingKey::PaymentChannels, ['bank_transfer', 'cash']);
    bankAccount();

    switchTo(systemAdmin())->getJson(PAYMENT_CHANNELS)
        ->assertOk()
        ->assertJsonPath('data.channels', [PaymentChannel::BankTransfer->value]);
});
