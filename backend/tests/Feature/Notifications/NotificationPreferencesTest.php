<?php

declare(strict_types=1);

use App\Data\Entities\EntityNotice;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationChannel;
use App\Enums\Notifications\NotificationCode;
use App\Models\Identity\User;
use App\Models\Notifications\Notification;
use App\Models\Notifications\NotificationPreference;
use App\Notifications\Channels\InAppChannel;
use App\Notifications\Entities\EntityNotification;
use App\Services\Entities\ErasedOwnerAnonymizer;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Events\NotificationSkipped;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

const MY_NOTIFICATION_PREFERENCES = '/api/v1/me/notification-preferences';

const NOTIFICATION_TYPES = '/api/v1/settings/notifications/types';

/**
 * @return array<string, mixed>|null
 */
function preferenceRow(TestResponse $response, NotificationCode $code): ?array
{
    $rows = collect($response->json('data'))->keyBy('code');

    return $rows->get($code->value);
}

function sendEntityNotice(User $user, NotificationCode $code): void
{
    $user->notify(new EntityNotification(new EntityNotice(
        $code,
        new LocalizedText('عنوان', 'Title'),
        new LocalizedText('نص', 'Body'),
        '/assessment/self',
        true,
        true,
    )));
}

it('lists the notification types the user receives with their channel states', function (): void {
    $owner = ownerOf(entityWithOwner());

    $response = signIn($owner)->getJson(MY_NOTIFICATION_PREFERENCES)->assertOk();

    expect(preferenceRow($response, NotificationCode::SelfAssessmentSubmitted))->toBe([
        'code' => 'SELF-03',
        'module' => 'SELF',
        'category' => 'informational',
        'is_mandatory' => false,
        'channels' => [
            ['channel' => 'in_app', 'enabled' => true, 'configurable' => false],
            ['channel' => 'email', 'enabled' => true, 'configurable' => true],
        ],
    ])
        ->and(preferenceRow($response, NotificationCode::PaymentSucceeded)['is_mandatory'] ?? null)->toBeTrue()
        ->and(preferenceRow($response, NotificationCode::OtpCode)['channels'] ?? null)->toBe([
            ['channel' => 'email', 'enabled' => true, 'configurable' => false],
            ['channel' => 'sms', 'enabled' => true, 'configurable' => false],
        ])
        ->and(preferenceRow($response, NotificationCode::AccreditedAwaitingAssignment))->toBeNull()
        ->and(preferenceRow($response, NotificationCode::SuperAdminLogin))->toBeNull();
});

it('lists only the security types for the Super Admin and the role types for staff', function (): void {
    $super = signIn(superAdmin())->getJson(MY_NOTIFICATION_PREFERENCES)->assertOk();
    $follower = switchTo(requestFollower())->getJson(MY_NOTIFICATION_PREFERENCES)->assertOk();

    expect(preferenceRow($super, NotificationCode::SuperAdminLogin))->not->toBeNull()
        ->and(preferenceRow($super, NotificationCode::SelfAssessmentSubmitted))->toBeNull()
        ->and(preferenceRow($follower, NotificationCode::AccreditedAwaitingAssignment))->not->toBeNull()
        ->and(preferenceRow($follower, NotificationCode::SelfAssessmentSubmitted))->toBeNull();
});

it('turns an optional e-mail off and on again', function (): void {
    $owner = ownerOf(entityWithOwner());

    $response = signIn($owner)->putJson(MY_NOTIFICATION_PREFERENCES, ['preferences' => [
        ['code' => 'SELF-03', 'channel' => 'email', 'enabled' => false],
        ['code' => 'ACC-01', 'channel' => 'email', 'enabled' => false],
    ]])->assertOk();

    expect(preferenceRow($response, NotificationCode::SelfAssessmentSubmitted)['channels'][1] ?? null)->toBe(['channel' => 'email', 'enabled' => false, 'configurable' => true])
        ->and(NotificationPreference::query()->where('user_id', $owner->id)->where('is_enabled', false)->count())->toBe(2);

    $this->putJson(MY_NOTIFICATION_PREFERENCES, ['preferences' => [
        ['code' => 'SELF-03', 'channel' => 'email', 'enabled' => false],
        ['code' => 'SELF-03', 'channel' => 'email', 'enabled' => true],
    ]])->assertOk();

    expect(NotificationPreference::query()->where('user_id', $owner->id)->where('code', 'SELF-03')->sole()->is_enabled)->toBeTrue();
});

it('refuses to turn off mandatory notices or types the user does not receive', function (string $code, string $channel, string $error, string $field): void {
    signIn(ownerOf(entityWithOwner()))->putJson(MY_NOTIFICATION_PREFERENCES, ['preferences' => [
        ['code' => 'SELF-03', 'channel' => 'email', 'enabled' => false],
        ['code' => $code, 'channel' => $channel, 'enabled' => false],
    ]])
        ->assertUnprocessable()
        ->assertJsonPath('code', $error)
        ->assertJsonValidationErrors([$field]);

    expect(NotificationPreference::query()->count())->toBe(0);
})->with([
    'payment success (official record)' => ['PAY-02', 'email', 'notification_preference.locked', 'preferences.1.channel'],
    'one-time code' => ['REG-02', 'sms', 'notification_preference.locked', 'preferences.1.channel'],
    'completion request with a deadline' => ['ACC-07', 'email', 'notification_preference.locked', 'preferences.1.channel'],
    'channel not used by the type' => ['SELF-01', 'email', 'notification_preference.locked', 'preferences.1.channel'],
    'staff-only type' => ['ACC-04', 'email', 'notification_preference.not_applicable', 'preferences.1.code'],
    'security alert' => ['SEC-01', 'email', 'notification_preference.not_applicable', 'preferences.1.code'],
]);

it('rejects malformed preference changes', function (array $payload, string $field): void {
    signIn(ownerOf(entityWithOwner()))->putJson(MY_NOTIFICATION_PREFERENCES, $payload)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors([$field]);
})->with([
    'empty list' => [['preferences' => []], 'preferences'],
    'in-app channel' => [['preferences' => [['code' => 'SELF-03', 'channel' => 'in_app', 'enabled' => false]]], 'preferences.0.channel'],
    'unknown code' => [['preferences' => [['code' => 'XYZ-01', 'channel' => 'email', 'enabled' => false]]], 'preferences.0.code'],
    'missing flag' => [['preferences' => [['code' => 'SELF-03', 'channel' => 'email']]], 'preferences.0.enabled'],
    'extra key' => [['preferences' => [['code' => 'SELF-03', 'channel' => 'email', 'enabled' => false, 'user_id' => 1]]], 'preferences.0'],
]);

it('requires a session for the preferences', function (): void {
    $this->getJson(MY_NOTIFICATION_PREFERENCES)->assertUnauthorized();
    $this->putJson(MY_NOTIFICATION_PREFERENCES, ['preferences' => []])->assertUnauthorized();
});

it('skips the e-mail of a type the recipient turned off and still delivers it in-app', function (): void {
    Event::fake([NotificationSent::class, NotificationSkipped::class]);
    $owner = ownerOf(entityWithOwner());
    $other = ownerOf(entityWithOwner());
    NotificationPreference::query()->create(['user_id' => $owner->id, 'code' => 'SELF-03', 'channel' => NotificationChannel::Email, 'is_enabled' => false]);

    sendEntityNotice($owner, NotificationCode::SelfAssessmentSubmitted);
    sendEntityNotice($other, NotificationCode::SelfAssessmentSubmitted);

    Event::assertDispatched(NotificationSkipped::class, static fn (NotificationSkipped $event): bool => $event->notifiable->is($owner) && $event->channel === 'mail');
    Event::assertDispatched(NotificationSent::class, static fn (NotificationSent $event): bool => $event->notifiable->is($owner) && $event->channel === InAppChannel::class);
    Event::assertDispatched(NotificationSent::class, static fn (NotificationSent $event): bool => $event->notifiable->is($other) && $event->channel === 'mail');
    expect(Notification::query()->where('user_id', $owner->id)->where('code', 'SELF-03')->count())->toBe(1);
});

it('always e-mails a mandatory type, even with a stored opt-out row', function (): void {
    Event::fake([NotificationSent::class, NotificationSkipped::class]);
    $owner = ownerOf(entityWithOwner());
    NotificationPreference::query()->create(['user_id' => $owner->id, 'code' => 'PAY-02', 'channel' => NotificationChannel::Email, 'is_enabled' => false]);

    sendEntityNotice($owner, NotificationCode::PaymentSucceeded);

    Event::assertDispatched(NotificationSent::class, static fn (NotificationSent $event): bool => $event->notifiable->is($owner) && $event->channel === 'mail');
    Event::assertNotDispatched(NotificationSkipped::class);
});

it('removes the preferences of an anonymized entity owner', function (): void {
    $entity = entityWithOwner();
    $owner = ownerOf($entity);
    NotificationPreference::query()->create(['user_id' => $owner->id, 'code' => 'SELF-03', 'channel' => NotificationChannel::Email, 'is_enabled' => false]);

    app(ErasedOwnerAnonymizer::class)->anonymize($owner, 'ERASED-000001');

    expect(NotificationPreference::query()->where('user_id', $owner->id)->exists())->toBeFalse();
});

it('shows the System Administrator the read-only notification type catalogue', function (): void {
    $response = signIn(systemAdmin())->getJson(NOTIFICATION_TYPES)
        ->assertOk()
        ->assertJsonPath('meta.total', count(NotificationCode::cases()));

    $types = collect($response->json('data'))->keyBy('code');

    expect($types->get('SEC-01'))->toBe([
        'code' => 'SEC-01',
        'module' => 'SEC',
        'category' => 'alert',
        'channels' => ['in_app', 'email'],
        'audiences' => ['super_admin'],
        'is_mandatory' => true,
        'user_configurable_channels' => [],
    ])
        ->and($types->get('ACC-05')['user_configurable_channels'] ?? null)->toBe(['email'])
        ->and($types->get('ADV-09')['audiences'] ?? null)->toBe(['committee_assessor']);

    $this->getJson(NOTIFICATION_TYPES.'?filter[module]=PAY')->assertOk()->assertJsonPath('meta.total', 8);
    $this->getJson(NOTIFICATION_TYPES.'?filter[category]=error')->assertOk()->assertJsonPath('meta.total', 0);
    $this->getJson(NOTIFICATION_TYPES.'?filter[audience]=content_officer')->assertOk()->assertJsonPath('meta.total', 5);
    $this->getJson(NOTIFICATION_TYPES.'?filter[module]=XYZ')->assertUnprocessable()->assertJsonValidationErrors(['filter.module']);
    $this->getJson(NOTIFICATION_TYPES.'?filter[audience]=customer_service')->assertUnprocessable()->assertJsonValidationErrors(['filter.audience']);
});

it('denies the catalogue to everyone without settings.notifications.manage', function (RoleKey $role): void {
    signIn($role === RoleKey::Entity ? entityUser() : staffUser($role))->getJson(NOTIFICATION_TYPES)->assertForbidden();
})->with([RoleKey::RequestFollower, RoleKey::FinanceOfficer, RoleKey::ContentOfficer, RoleKey::Entity]);
