<?php

declare(strict_types=1);

use App\Data\Criteria\CriteriaNotice;
use App\Data\Entities\EntityNotice;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Listeners\Notifications\SkipDisabledDeliveryChannel;
use App\Models\Notifications\NotificationChannelSetting;
use App\Notifications\Channels\InAppChannel;
use App\Notifications\Criteria\CriteriaNotification;
use App\Notifications\Entities\EntityNotification;
use Illuminate\Notifications\Events\NotificationSending;

const NOTIFICATION_CHANNELS = '/api/v1/settings/notifications/types';

/**
 * @return array<string, array<string, mixed>>
 */
function channelStates(string $code): array
{
    return collect(switchTo(systemAdmin())->getJson(NOTIFICATION_CHANNELS.'/'.$code)->assertOk()->json('data.channel_settings'))->keyBy('channel')->all();
}

function registeredNotice(): EntityNotification
{
    return new EntityNotification(EntityNotice::for(NotificationCode::EntityRegistered, EntityNotice::FOR_STAFF, ['entity' => 'جمعية الأمل الخيرية', 'reference' => 'ENT-000001'], 'admin/entities/1'));
}

function deliveryAllowed(object $notification, string $channel): ?bool
{
    return app(SkipDisabledDeliveryChannel::class)->handle(new NotificationSending(systemAdmin(), $notification, $channel));
}

it('shows the channel switches of a type, locked for a mandatory one', function (): void {
    $registered = channelStates('REG-01');
    $invitation = channelStates('USR-01');

    expect($registered['in_app'])->toBe(['channel' => 'in_app', 'is_enabled' => true, 'is_shipped' => true, 'is_switchable' => true])
        ->and($registered['email']['is_enabled'])->toBeTrue()
        ->and($registered['sms'])->toBe(['channel' => 'sms', 'is_enabled' => false, 'is_shipped' => false, 'is_switchable' => true])
        ->and(collect($invitation)->pluck('is_switchable')->unique()->all())->toBe([false]);
});

it('switches e-mail off for a type, so it is no longer sent to anyone', function (): void {
    switchTo(systemAdmin())->patchJson(NOTIFICATION_CHANNELS.'/REG-01/channels', ['channels' => ['email' => false]])
        ->assertOk()
        ->assertJsonPath('data.channel_settings.1.is_enabled', false);

    expect(NotificationChannelSetting::query()->sole()->is_enabled)->toBeFalse()
        ->and(deliveryAllowed(registeredNotice(), 'mail'))->toBeFalse()
        ->and(deliveryAllowed(registeredNotice(), InAppChannel::class))->toBeNull();
});

it('switches e-mail on for an in-app only type and sends its notice by e-mail too', function (): void {
    $notification = new CriteriaNotification(CriteriaNotice::for(NotificationCode::CriteriaMainCreated, ['name' => 'القيادة'], 'admin/criteria', [RoleKey::CriteriaOfficer]));
    $recipient = staffUser(RoleKey::CriteriaOfficer);
    expect($notification->via($recipient))->toBe([InAppChannel::class]);

    switchTo(systemAdmin())->patchJson(NOTIFICATION_CHANNELS.'/STD-01/channels', ['channels' => ['email' => true]])->assertOk();

    expect($notification->via($recipient))->toBe([InAppChannel::class, 'mail'])
        ->and(implode(' ', array_map(strval(...), $notification->toMail($recipient)->introLines)))->toContain('القيادة');
});

it('goes back to the shipped channels when the shipped state is chosen again', function (): void {
    switchTo(systemAdmin())->patchJson(NOTIFICATION_CHANNELS.'/REG-01/channels', ['channels' => ['email' => false, 'in_app' => true]])->assertOk();
    expect(NotificationChannelSetting::query()->count())->toBe(1);

    switchTo(systemAdmin())->patchJson(NOTIFICATION_CHANNELS.'/REG-01/channels', ['channels' => ['email' => true]])->assertOk();

    expect(NotificationChannelSetting::query()->count())->toBe(0)
        ->and(deliveryAllowed(registeredNotice(), 'mail'))->toBeNull();
});

it('reports the enabled channels in the catalogue list', function (): void {
    switchTo(systemAdmin())->patchJson(NOTIFICATION_CHANNELS.'/REG-01/channels', ['channels' => ['email' => false]])->assertOk();

    $row = collect(switchTo(systemAdmin())->getJson(NOTIFICATION_CHANNELS)->assertOk()->json('data'))->firstWhere('code', 'REG-01');

    expect($row['channels'])->toBe(['in_app', 'email'])
        ->and($row['enabled_channels'])->toBe(['in_app']);
});

it('refuses to switch the channels of a mandatory type or an unknown channel', function (string $code, array $channels, string $field): void {
    switchTo(systemAdmin())->patchJson(NOTIFICATION_CHANNELS.'/'.$code.'/channels', ['channels' => $channels])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    expect(NotificationChannelSetting::query()->count())->toBe(0);
})->with([
    'mandatory' => ['USR-01', ['email' => false], 'channels'],
    'unknown channel' => ['REG-01', ['push' => true], 'channels'],
    'not a boolean' => ['REG-01', ['email' => 'later'], 'channels.email'],
]);

it('answers 404 for an unknown type and 403 without the settings permission', function (): void {
    switchTo(systemAdmin())->patchJson(NOTIFICATION_CHANNELS.'/XYZ-99/channels', ['channels' => ['email' => false]])->assertNotFound();
    switchTo(staffUser(RoleKey::Assessor))->patchJson(NOTIFICATION_CHANNELS.'/REG-01/channels', ['channels' => ['email' => false]])->assertForbidden();
});
