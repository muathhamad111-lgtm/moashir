<?php

declare(strict_types=1);

use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Models\Identity\Role;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

const NOTIFICATIONS = '/api/v1/notifications';

it('lists only the signed-in user\'s inbox, newest first, with the unread count', function (): void {
    $owner = ownerOf(entityWithOwner());
    $older = inAppNotification($owner, NotificationCode::SelfAssessmentStarted, ['created_at' => Date::now()->subDay()]);
    $newer = inAppNotification($owner, NotificationCode::SelfAssessmentSubmitted);
    inAppNotification($owner, NotificationCode::SelfAssessmentDraftSaved, ['archived_at' => Date::now()]);
    inAppNotification(ownerOf(entityWithOwner()));
    inAppNotification(systemAdmin(), NotificationCode::EntityRegistered);

    signIn($owner)->getJson(NOTIFICATIONS)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.0.code', 'SELF-03')
        ->assertJsonPath('data.0.module', 'SELF')
        ->assertJsonPath('data.0.category', 'informational')
        ->assertJsonPath('data.0.title.ar', 'تم إرسال التقييم الذاتي')
        ->assertJsonPath('data.0.title.en', 'Self assessment submitted')
        ->assertJsonPath('data.0.link', '/assessment/self')
        ->assertJsonPath('data.0.is_read', false)
        ->assertJsonPath('data.0.archived_at', null)
        ->assertJsonPath('data.1.id', $older->id)
        ->assertJsonPath('meta.unread_count', 2)
        ->assertJsonPath('meta.pagination.total', 2);
});

it('filters the inbox by category, read state, code, archive and search, and pages it', function (): void {
    $owner = systemAdmin();
    $alert = inAppNotification($owner, NotificationCode::ExternalServiceDown);
    $read = inAppNotification($owner, NotificationCode::EntityRegistered, ['read_at' => Date::now()]);
    $archived = inAppNotification($owner, NotificationCode::CriteriaMainCreated, ['archived_at' => Date::now()]);
    inAppNotification($owner, NotificationCode::CriteriaMainCreated, [
        'title' => ['ar' => 'إنشاء معيار رئيسي جديد', 'en' => 'New main criterion created'],
    ]);

    signIn($owner);
    $this->getJson(NOTIFICATIONS.'?filter[category]=alert')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $alert->id);
    $this->getJson(NOTIFICATIONS.'?filter[read]=true')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $read->id);
    $this->getJson(NOTIFICATIONS.'?filter[read]=false')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson(NOTIFICATIONS.'?filter[archived]=true')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $archived->id);
    $this->getJson(NOTIFICATIONS.'?filter[code]=REG-01')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $read->id);
    $this->getJson(NOTIFICATIONS.'?search='.urlencode('معيار رئيسي'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'STD-01');
    $this->getJson(NOTIFICATIONS.'?per_page=2&page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.pagination.last_page', 2);
});

it('rejects invalid inbox filters', function (string $query, string $field): void {
    signIn(systemAdmin())->getJson(NOTIFICATIONS.'?'.$query)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors([$field]);
})->with([
    'unknown category' => ['filter[category]=urgent', 'filter.category'],
    'unknown code' => ['filter[code]=XYZ-99', 'filter.code'],
    'read flag' => ['filter[read]=maybe', 'filter.read'],
    'unknown filter' => ['filter[recipient_role]=entity', 'filter'],
    'short search' => ['search=a', 'search'],
    'page size' => ['per_page=500', 'per_page'],
]);

it('counts unread inbox notifications by category', function (): void {
    $owner = systemAdmin();
    inAppNotification($owner, NotificationCode::ExternalServiceDown);
    inAppNotification($owner, NotificationCode::EntityRegistered);
    inAppNotification($owner, NotificationCode::EntityRegistered, ['read_at' => Date::now()]);
    inAppNotification($owner, NotificationCode::ExternalServiceDown, ['archived_at' => Date::now()]);
    inAppNotification(staffUser(RoleKey::FinanceOfficer), NotificationCode::ExternalServiceDown);

    signIn($owner)->getJson(NOTIFICATIONS.'/unread-count')
        ->assertOk()
        ->assertJsonPath('data.count', 2)
        ->assertJsonPath('data.by_category.alert', 1)
        ->assertJsonPath('data.by_category.action_required', 1)
        ->assertJsonPath('data.by_category.informational', 0)
        ->assertJsonPath('data.by_category.error', 0);
});

it('marks one notification as read idempotently', function (): void {
    Date::setTestNow('2026-11-03 09:00:00');
    $owner = ownerOf(entityWithOwner());
    $notification = inAppNotification($owner);

    signIn($owner)->patchJson(NOTIFICATIONS.'/'.$notification->id.'/read')
        ->assertOk()
        ->assertJsonPath('data.is_read', true)
        ->assertJsonPath('data.read_at', '2026-11-03T09:00:00+00:00');

    Date::setTestNow('2026-11-03 10:00:00');
    $this->patchJson(NOTIFICATIONS.'/'.$notification->id.'/read')->assertOk()->assertJsonPath('data.read_at', '2026-11-03T09:00:00+00:00');
});

it('archives a notification and keeps it out of the inbox and the unread count', function (): void {
    $owner = ownerOf(entityWithOwner());
    $notification = inAppNotification($owner);

    signIn($owner)->patchJson(NOTIFICATIONS.'/'.$notification->id.'/archive')->assertOk()->assertJsonPath('data.is_read', false);

    expect($notification->refresh()->archived_at)->not->toBeNull();
    $this->getJson(NOTIFICATIONS)->assertOk()->assertJsonCount(0, 'data');
    $this->getJson(NOTIFICATIONS.'/unread-count')->assertOk()->assertJsonPath('data.count', 0);
    $this->getJson(NOTIFICATIONS.'?filter[archived]=true')->assertOk()->assertJsonPath('data.0.id', $notification->id);
});

it('marks every unread inbox notification as read, optionally by category', function (): void {
    $owner = systemAdmin();
    inAppNotification($owner, NotificationCode::ExternalServiceDown);
    inAppNotification($owner, NotificationCode::EntityRegistered);
    $archived = inAppNotification($owner, NotificationCode::EntityRegistered, ['archived_at' => Date::now()]);
    $foreign = inAppNotification(staffUser(RoleKey::FinanceOfficer), NotificationCode::ExternalServiceDown);

    signIn($owner)->postJson(NOTIFICATIONS.'/read-all', ['category' => 'alert'])->assertOk()->assertJsonPath('data.updated', 1);
    $this->postJson(NOTIFICATIONS.'/read-all')->assertOk()->assertJsonPath('data.updated', 1);
    $this->postJson(NOTIFICATIONS.'/read-all', ['category' => 'urgent'])->assertUnprocessable()->assertJsonValidationErrors(['category']);

    expect($archived->refresh()->read_at)->toBeNull()
        ->and($foreign->refresh()->read_at)->toBeNull();
});

it('never reads or changes another user\'s notification, not even for the Super Admin', function (): void {
    $foreign = inAppNotification(ownerOf(entityWithOwner()));

    foreach ([ownerOf(entityWithOwner()), systemAdmin(), superAdmin()] as $intruder) {
        switchTo($intruder)->patchJson(NOTIFICATIONS.'/'.$foreign->id.'/read')->assertNotFound();
        $this->patchJson(NOTIFICATIONS.'/'.$foreign->id.'/archive')->assertNotFound();
        $this->getJson(NOTIFICATIONS)->assertOk()->assertJsonMissing(['id' => $foreign->id]);
    }

    expect($foreign->refresh()->read_at)->toBeNull()->and($foreign->archived_at)->toBeNull();
});

it('answers 404 for an unknown or malformed notification id', function (): void {
    signIn(systemAdmin());

    $this->patchJson(NOTIFICATIONS.'/'.Str::uuid()->toString().'/read')->assertNotFound();
    $this->patchJson(NOTIFICATIONS.'/not-a-uuid/read')->assertNotFound();
});

it('requires a session and the notifications.view permission', function (): void {
    $this->getJson(NOTIFICATIONS)->assertUnauthorized();

    $admin = systemAdmin();
    Role::findByName(RoleKey::SystemAdmin->value, 'web')->revokePermissionTo('notifications.view');

    signIn($admin->refresh())->getJson(NOTIFICATIONS)->assertForbidden();
    $this->getJson(NOTIFICATIONS.'/unread-count')->assertForbidden();
    $this->postJson(NOTIFICATIONS.'/read-all')->assertForbidden();
});

it('lets the Super Admin read their own security alerts', function (): void {
    $superAdmin = superAdmin();
    $alert = inAppNotification($superAdmin, NotificationCode::SuperAdminLogin);

    signIn($superAdmin)->getJson(NOTIFICATIONS)->assertOk()->assertJsonPath('data.0.id', $alert->id)->assertJsonPath('data.0.category', 'alert');
    $this->patchJson(NOTIFICATIONS.'/'.$alert->id.'/read')->assertOk()->assertJsonPath('data.is_read', true);
});
