<?php

declare(strict_types=1);

use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Models\Audit\Audit;
use App\Models\Notifications\NotificationChannelSetting;

const NOTIFICATION_BULK = '/api/v1/settings/notifications/channels';

/**
 * @return array<string, array<string, bool>>
 */
function listedStates(string $code): array
{
    return collect(switchTo(systemAdmin())->getJson('/api/v1/settings/notifications/types')->assertOk()->json('data'))
        ->firstWhere('code', $code)['channel_states'];
}

it('switches e-mail off for every type of a module and leaves its core types alone', function (): void {
    $response = switchTo(systemAdmin())->patchJson(NOTIFICATION_BULK, ['scope' => 'module', 'module' => 'REG', 'channels' => ['email'], 'enabled' => false])->assertOk();
    $nonCore = array_filter(NotificationCode::cases(), static fn (NotificationCode $code): bool => $code->module() === 'REG' && ! $code->isCore());

    expect($response->json('meta.updated'))->toBe(count($nonCore))
        ->and(listedStates('REG-01')['beneficiary']['email'])->toBeFalse()
        ->and(listedStates('REG-01')['management']['email'])->toBeFalse()
        ->and(listedStates('REG-02')['beneficiary']['email'])->toBeTrue()
        ->and(NotificationChannelSetting::query()->whereIn('code', ['REG-02', 'REG-07', 'REG-08'])->exists())->toBeFalse();
});

it('switches every channel off for management only on the named types', function (): void {
    switchTo(systemAdmin())->patchJson(NOTIFICATION_BULK, ['scope' => 'codes', 'codes' => ['REG-01', 'USR-01'], 'audiences' => ['management'], 'enabled' => false])
        ->assertOk()
        ->assertJsonPath('meta.updated', 1);

    expect(listedStates('REG-01')['management'])->toBe(['in_app' => false, 'email' => false, 'sms' => false])
        ->and(listedStates('REG-01')['beneficiary'])->toBe(['in_app' => true, 'email' => true, 'sms' => false])
        ->and(NotificationChannelSetting::query()->where('code', 'USR-01')->exists())->toBeFalse();
});

it('turns a channel back on for everything and records each change in the audit log', function (): void {
    switchTo(systemAdmin())->patchJson(NOTIFICATION_BULK, ['scope' => 'all', 'channels' => ['in_app'], 'enabled' => false])->assertOk();
    switchTo(systemAdmin())->patchJson(NOTIFICATION_BULK, ['scope' => 'all', 'channels' => ['in_app'], 'enabled' => true])->assertOk();

    expect(NotificationChannelSetting::query()->where('channel', 'in_app')->where('is_enabled', false)->exists())->toBeFalse()
        ->and(Audit::query()->where('auditable_type', (new NotificationChannelSetting)->getMorphClass())->where('event', 'created')->exists())->toBeTrue()
        ->and(Audit::query()->where('auditable_type', (new NotificationChannelSetting)->getMorphClass())->where('event', 'deleted')->exists())->toBeTrue();
});

it('refuses a bulk switch with a missing or unknown scope, channel or audience', function (array $payload, string $field): void {
    switchTo(systemAdmin())->patchJson(NOTIFICATION_BULK, $payload)->assertUnprocessable()->assertJsonValidationErrors([$field]);

    expect(NotificationChannelSetting::query()->count())->toBe(0);
})->with([
    'no scope' => [['enabled' => false], 'scope'],
    'codes without codes' => [['scope' => 'codes', 'enabled' => false], 'codes'],
    'unknown code' => [['scope' => 'codes', 'codes' => ['XYZ-99'], 'enabled' => false], 'codes.0'],
    'unknown module' => [['scope' => 'module', 'module' => 'NOPE', 'enabled' => false], 'module'],
    'unknown channel' => [['scope' => 'all', 'channels' => ['push'], 'enabled' => false], 'channels.0'],
    'unknown audience' => [['scope' => 'all', 'audiences' => ['public'], 'enabled' => false], 'audiences.0'],
    'no state' => [['scope' => 'all'], 'enabled'],
]);

it('answers 403 without the settings permission and 401 for a guest', function (): void {
    $this->patchJson(NOTIFICATION_BULK, ['scope' => 'all', 'enabled' => false])->assertUnauthorized();
    switchTo(staffUser(RoleKey::Assessor))->patchJson(NOTIFICATION_BULK, ['scope' => 'all', 'enabled' => false])->assertForbidden();

    expect(NotificationChannelSetting::query()->count())->toBe(0);
});
