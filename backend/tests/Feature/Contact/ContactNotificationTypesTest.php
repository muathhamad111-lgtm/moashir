<?php

declare(strict_types=1);

use App\Enums\Notifications\NotificationAudience;
use App\Enums\Notifications\NotificationCode;
use App\Services\Notifications\NotificationTypeCatalog;

it('catalogues the contact notices with channels, audiences and mandatory flags', function (): void {
    $catalog = new NotificationTypeCatalog;

    expect($catalog->of(NotificationCode::ContactMessageReceived)->audiences)->toBe([NotificationAudience::ContactHandler])
        ->and($catalog->of(NotificationCode::ContactMessageReceived)->isMandatory)->toBeFalse()
        ->and($catalog->of(NotificationCode::ContactReplySent)->isMandatory)->toBeTrue()
        ->and($catalog->of(NotificationCode::ContactMessageAcknowledged)->channels)->toHaveCount(1)
        ->and($catalog->of(NotificationCode::CertificateSuspended)->audiences)->toBe([NotificationAudience::Entity, NotificationAudience::SystemAdmin])
        ->and(NotificationCode::ContactMessageReceived->module())->toBe('CNT')
        ->and(NotificationCode::ContactMessageReceived->category()->value)->toBe('action_required');
});

it('offers the handler notices to every holder of the inbox permission', function (): void {
    $preferences = collect(switchTo(contentOfficer())->getJson('/api/v1/me/notification-preferences')->assertOk()->json('data'))->keyBy('code');

    expect($preferences->has('CNT-01'))->toBeTrue()
        ->and($preferences->get('CNT-01')['is_mandatory'])->toBeFalse()
        ->and(collect($preferences->get('CNT-01')['channels'])->firstWhere('channel', 'email')['configurable'])->toBeTrue();

    switchTo(contentOfficer())->putJson('/api/v1/me/notification-preferences', ['preferences' => [['code' => 'CNT-01', 'channel' => 'email', 'enabled' => false]]])->assertOk();
    switchTo(criteriaOfficer())->putJson('/api/v1/me/notification-preferences', ['preferences' => [['code' => 'CNT-01', 'channel' => 'email', 'enabled' => false]]])->assertUnprocessable()
        ->assertJsonPath('code', 'notification_preference.not_applicable');
});

it('keeps the reply to a sender mandatory', function (): void {
    switchTo(ownerOf(entityWithOwner()))->putJson('/api/v1/me/notification-preferences', ['preferences' => [['code' => 'CNT-02', 'channel' => 'email', 'enabled' => false]]])->assertUnprocessable()
        ->assertJsonPath('code', 'notification_preference.locked');
});
