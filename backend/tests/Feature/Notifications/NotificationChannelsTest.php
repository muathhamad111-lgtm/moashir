<?php

declare(strict_types=1);

use App\Data\Criteria\CriteriaNotice;
use App\Data\Entities\EntityNotice;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationAudienceKind;
use App\Enums\Notifications\NotificationCode;
use App\Listeners\Notifications\SkipDisabledDeliveryChannel;
use App\Models\Identity\User;
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
    return collect(switchTo(systemAdmin())->getJson(NOTIFICATION_CHANNELS.'/'.$code)->assertOk()->json('data.channel_settings'))
        ->keyBy(static fn (array $state): string => $state['audience'].'.'.$state['channel'])
        ->all();
}

function registeredNotice(): EntityNotification
{
    return new EntityNotification(EntityNotice::for(NotificationCode::EntityRegistered, EntityNotice::FOR_STAFF, ['entity' => 'جمعية الأمل الخيرية', 'reference' => 'ENT-000001'], 'admin/entities/1'));
}

function deliveryAllowed(object $notification, string $channel, ?User $recipient = null): ?bool
{
    return app(SkipDisabledDeliveryChannel::class)->handle(new NotificationSending($recipient ?? systemAdmin(), $notification, $channel));
}

it('shows the channel switches of a type per audience, locked for a core one', function (): void {
    $registered = channelStates('REG-01');
    $invitation = channelStates('USR-01');

    expect(array_keys($registered))->toBe(['beneficiary.in_app', 'beneficiary.email', 'beneficiary.sms', 'management.in_app', 'management.email', 'management.sms'])
        ->and($registered['beneficiary.in_app'])->toBe(['channel' => 'in_app', 'audience' => 'beneficiary', 'is_enabled' => true, 'is_shipped' => true, 'is_switchable' => true])
        ->and($registered['management.email']['is_enabled'])->toBeTrue()
        ->and($registered['management.sms'])->toBe(['channel' => 'sms', 'audience' => 'management', 'is_enabled' => false, 'is_shipped' => false, 'is_switchable' => true])
        ->and(collect($invitation)->pluck('is_switchable')->unique()->all())->toBe([false]);
});

it('switches e-mail off for every audience of a type, so it is no longer sent to anyone', function (): void {
    switchTo(systemAdmin())->patchJson(NOTIFICATION_CHANNELS.'/REG-01/channels', ['channels' => ['email' => false]])->assertOk();

    expect(NotificationChannelSetting::query()->pluck('audience')->map->value->sort()->values()->all())->toBe(['beneficiary', 'management'])
        ->and(deliveryAllowed(registeredNotice(), 'mail'))->toBeFalse()
        ->and(deliveryAllowed(registeredNotice(), 'mail', entityUser()))->toBeFalse()
        ->and(deliveryAllowed(registeredNotice(), InAppChannel::class))->toBeNull();
});

it('switches e-mail off for management only and keeps sending it to the beneficiary', function (): void {
    switchTo(systemAdmin())->patchJson(NOTIFICATION_CHANNELS.'/REG-01/channels', ['audience' => 'management', 'channels' => ['email' => false]])->assertOk();

    expect(channelStates('REG-01')['management.email']['is_enabled'])->toBeFalse()
        ->and(channelStates('REG-01')['beneficiary.email']['is_enabled'])->toBeTrue()
        ->and(deliveryAllowed(registeredNotice(), 'mail'))->toBeFalse()
        ->and(deliveryAllowed(registeredNotice(), 'mail', entityUser()))->toBeNull();
});

it('lets the administrator switch off a type that used to be mandatory', function (): void {
    switchTo(systemAdmin())->patchJson(NOTIFICATION_CHANNELS.'/ACC-07/channels', ['channels' => ['email' => false]])->assertOk();

    expect(channelStates('ACC-07')['beneficiary.email']['is_enabled'])->toBeFalse();
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
    expect(NotificationChannelSetting::query()->count())->toBe(2);

    switchTo(systemAdmin())->patchJson(NOTIFICATION_CHANNELS.'/REG-01/channels', ['channels' => ['email' => true]])->assertOk();

    expect(NotificationChannelSetting::query()->count())->toBe(0)
        ->and(deliveryAllowed(registeredNotice(), 'mail'))->toBeNull();
});

it('reports the enabled channels in the catalogue list', function (): void {
    switchTo(systemAdmin())->patchJson(NOTIFICATION_CHANNELS.'/REG-01/channels', ['channels' => ['email' => false]])->assertOk();

    $row = collect(switchTo(systemAdmin())->getJson(NOTIFICATION_CHANNELS)->assertOk()->json('data'))->firstWhere('code', 'REG-01');

    expect($row['channels'])->toBe(['in_app', 'email'])
        ->and($row['enabled_channels'])->toBe(['in_app'])
        ->and($row['is_core'])->toBeFalse()
        ->and($row['audience_kinds'])->toBe(['beneficiary', 'management'])
        ->and($row['channel_states']['management'])->toBe(['in_app' => true, 'email' => false, 'sms' => false]);
});

it('refuses to switch the channels of a core type, an unknown channel or an audience the type never reaches', function (string $code, array $payload, string $field): void {
    switchTo(systemAdmin())->patchJson(NOTIFICATION_CHANNELS.'/'.$code.'/channels', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    expect(NotificationChannelSetting::query()->count())->toBe(0);
})->with([
    'core' => ['USR-01', ['channels' => ['email' => false]], 'channels'],
    'unknown channel' => ['REG-01', ['channels' => ['push' => true]], 'channels'],
    'not a boolean' => ['REG-01', ['channels' => ['email' => 'later']], 'channels.email'],
    'audience not reached' => ['STD-01', ['audience' => 'beneficiary', 'channels' => ['email' => true]], 'audience'],
    'unknown audience' => ['REG-01', ['audience' => 'public', 'channels' => ['email' => true]], 'audience'],
]);

it('reads a recipient loaded without its roles as staff or beneficiary without lazy loading', function (): void {
    $staff = User::query()->findOrFail(staffUser(RoleKey::CriteriaOfficer)->id);
    $entity = User::query()->findOrFail(entityUser()->id);

    expect(NotificationAudienceKind::of($staff))->toBe(NotificationAudienceKind::Management)
        ->and(NotificationAudienceKind::of($entity))->toBe(NotificationAudienceKind::Beneficiary);
});

it('answers 404 for an unknown type and 403 without the settings permission', function (): void {
    switchTo(systemAdmin())->patchJson(NOTIFICATION_CHANNELS.'/XYZ-99/channels', ['channels' => ['email' => false]])->assertNotFound();
    switchTo(staffUser(RoleKey::Assessor))->patchJson(NOTIFICATION_CHANNELS.'/REG-01/channels', ['channels' => ['email' => false]])->assertForbidden();
});
