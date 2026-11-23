<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Models\Audit\Audit;
use App\Models\Nda\NdaReminder;
use App\Notifications\Entities\EntityNotification;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

function isNdaReminder(EntityNotification $notification): bool
{
    return $notification->notice->code === NotificationCode::NdaAcceptanceReminder;
}

it('sends NDA-02 once the delay has passed, once per version, to those still pending at sending time', function (): void {
    $version = publishedNda();
    $pending = staffUser(RoleKey::Assessor);
    $accepted = staffUser(RoleKey::CriteriaOfficer);
    acceptNdaAs($accepted, $version);

    $this->artisan('moashir:send-nda-reminders')->assertSuccessful();
    Notification::assertNothingSent();

    Date::setTestNow(now()->addDays(3)->addMinute());
    $this->artisan('moashir:send-nda-reminders')->assertSuccessful();
    $this->artisan('moashir:send-nda-reminders')->assertSuccessful();

    Notification::assertSentToTimes($pending, EntityNotification::class, 1);
    Notification::assertNotSentTo($accepted, EntityNotification::class);
    expect(NdaReminder::query()->count())->toBe(1)
        ->and(Audit::query()->where('event', AuditEvent::NdaReminderSent->value)->count())->toBe(1);
});

it('lets an administrator remind the pending staff at most once every 24 hours', function (): void {
    $version = publishedNda();
    $admin = systemAdmin();
    acceptNdaAs($admin, $version);
    $pending = staffUser(RoleKey::Assessor);

    signIn($admin)->postJson('/api/v1/settings/nda/reminders')->assertAccepted()->assertJsonPath('data.recipients', 1);
    signIn($admin)->postJson('/api/v1/settings/nda/reminders')->assertTooManyRequests()->assertJsonPath('code', 'nda.reminder_too_soon');

    Notification::assertSentTo($pending, EntityNotification::class, isNdaReminder(...));

    Date::setTestNow(now()->addHours(24)->addMinute());
    signIn($admin)->postJson('/api/v1/settings/nda/reminders')->assertAccepted();
});

it('has nothing to remind about before a version is published', function (): void {
    signIn(systemAdmin())->postJson('/api/v1/settings/nda/reminders')->assertConflict()->assertJsonPath('code', 'nda.no_current_version');
});
