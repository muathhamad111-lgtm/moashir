<?php

declare(strict_types=1);

use App\Actions\Assessments\RemindInactivePaidDrafts;
use App\Actions\Periods\SendPeriodDigest;
use App\Actions\Periods\SendPeriodNotices;
use App\Enums\Notifications\NotificationCode;
use App\Enums\Settings\SettingKey;
use App\Models\Assessments\PeriodNotice;
use App\Models\Identity\User;
use App\Notifications\Channels\InAppChannel;
use App\Notifications\Entities\EntityNotification;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

function sendPeriodNotices(): int
{
    return app(SendPeriodNotices::class)->handle();
}

function periodNoticeOf(User $owner, NotificationCode $code): ?EntityNotification
{
    $found = null;

    Notification::assertSentTo($owner, EntityNotification::class, static function (EntityNotification $notification) use ($code, &$found): bool {
        if ($notification->notice->code === $code) {
            $found = $notification;
        }

        return true;
    });

    return $found;
}

it('announces the next period once to every active verified entity from its notice date', function (): void {
    atRiyadh('2026-12-17 10:00');
    livePeriods();
    $entity = entityWithOwner();
    $pending = entityWithOwner(static fn ($factory) => $factory->pendingVerification());
    $deactivated = entityWithOwner(static fn ($factory) => $factory->deactivated());

    expect(sendPeriodNotices())->toBe(0);

    atRiyadh('2026-12-18 09:00');
    expect(sendPeriodNotices())->toBe(1)
        ->and(sendPeriodNotices())->toBe(0);

    $notice = periodNoticeOf(ownerOf($entity), NotificationCode::PeriodStartingSoon);
    expect($notice?->notice->link)->toBe('/paths/accredited')
        ->and($notice?->notice->body->ar)->toContain('2027-01-01')
        ->and($notice?->notice->title->en)->toContain('Q1 2027')
        ->and($notice?->via(ownerOf($entity)))->toContain('mail')
        ->and(periodByKey('2027-Q1')->notice_sent_at)->not->toBeNull();
    Notification::assertNotSentTo([ownerOf($pending), ownerOf($deactivated)], EntityNotification::class);
});

it('reminds the entities without a submission before the period ends, once per threshold', function (): void {
    atRiyadh('2027-02-01 10:00');
    livePeriods();
    $version = publishedAccreditedCriteria();
    $idle = entityWithOwner();
    $drafting = readyAccreditedDraft($version);
    $submitted = submitDraft(readyAccreditedDraft($version));

    atRiyadh('2027-03-17 09:00');
    expect(sendPeriodNotices())->toBe(2)
        ->and(sendPeriodNotices())->toBe(0);

    expect(periodNoticeOf(ownerOf($idle), NotificationCode::PeriodEndingSoon)?->notice->link)->toBe('/paths/accredited')
        ->and(periodNoticeOf(ownerOf($drafting->entity), NotificationCode::PeriodEndingSoon)?->notice->link)->toBe("/paths/accredited/{$drafting->id}?tab=request")
        ->and(periodNoticeOf(ownerOf($drafting->entity), NotificationCode::PeriodEndingSoon)?->notice->body->ar)->toContain($drafting->request_number)
        ->and(financeNoticeSent(ownerOf($submitted->entity), NotificationCode::PeriodEndingSoon))->toBeFalse();

    atRiyadh('2027-03-24 09:00');
    expect(sendPeriodNotices())->toBe(5)
        ->and(PeriodNotice::query()->where('code', NotificationCode::PeriodEndingSoon)->where('threshold_days', 7)->count())->toBe(2)
        ->and(PeriodNotice::query()->where('code', NotificationCode::PeriodStartingSoon)->count())->toBe(3)
        ->and(periodByKey('2027-Q1')->reminders_sent)->toEqualCanonicalizing([14, 7]);
});

it('sends only the closest reminder when several thresholds became due together', function (): void {
    atRiyadh('2027-02-01 10:00');
    livePeriods();
    entityWithOwner();

    atRiyadh('2027-03-30 09:00');
    expect(sendPeriodNotices())->toBe(2)
        ->and(PeriodNotice::query()->where('code', NotificationCode::PeriodEndingSoon)->sole()->threshold_days)->toBe(1)
        ->and(periodByKey('2027-Q1')->reminders_sent)->toEqualCanonicalizing([14, 7, 1])
        ->and(sendPeriodNotices())->toBe(0);
});

it('sends the staff digest once a day while a period is open', function (): void {
    atRiyadh('2026-12-20 10:00');
    livePeriods();
    $follower = requestFollower();
    $admin = systemAdmin();
    entityWithOwner();

    expect(app(SendPeriodDigest::class)->handle())->toBeFalse();

    atRiyadh('2027-03-26 08:00');
    generatePeriods();
    expect(app(SendPeriodDigest::class)->handle())->toBeTrue()
        ->and(app(SendPeriodDigest::class)->handle())->toBeFalse();

    $digest = periodNoticeOf($follower, NotificationCode::PeriodDigest);
    expect($digest?->via($follower))->toBe([InAppChannel::class])
        ->and($digest?->notice->body->en)->toContain('Entities that have not submitted: 1.')
        ->and(financeNoticeSent($admin, NotificationCode::PeriodDigest))->toBeTrue();

    atRiyadh('2027-03-27 08:00');
    expect(app(SendPeriodDigest::class)->handle())->toBeTrue();
});

it('reminds an inactive paid draft automatically once per reminder window', function (): void {
    atRiyadh('2027-02-01 10:00');
    livePeriods();
    $version = publishedAccreditedCriteria();
    $draft = readyAccreditedDraft($version);
    openedAccreditedRequest();

    atRiyadh('2027-02-05 10:00');
    expect(app(RemindInactivePaidDrafts::class)->handle())->toBe(0);
    $active = readyAccreditedDraft($version);

    atRiyadh('2027-02-09 10:00');
    expect(app(RemindInactivePaidDrafts::class)->handle())->toBe(2)
        ->and(app(RemindInactivePaidDrafts::class)->handle())->toBe(0);

    $notice = periodNoticeOf(ownerOf($draft->entity), NotificationCode::AccreditedPaidDraftReminder);
    expect($notice?->notice->link)->toBe("/paths/accredited/{$draft->id}?tab=request")
        ->and($draft->refresh()->paid_draft_reminded_at)->not->toBeNull()
        ->and($active->refresh()->paid_draft_reminded_at)->toBeNull();

    atRiyadh('2027-02-17 10:00');
    expect(app(RemindInactivePaidDrafts::class)->handle())->toBe(3);
});

it('runs the period commands from the console and schedules them daily without overlapping', function (): void {
    atRiyadh('2027-02-01 10:00');
    setPeriodicSetting(SettingKey::PeriodicRolloutFrom, PERIOD_ROLLOUT);

    test()->artisan('moashir:periods:generate')->assertSuccessful()->expectsOutputToContain('0 superseded');
    test()->artisan('moashir:periods:notify')->assertSuccessful();
    test()->artisan('moashir:periods:digest')->assertSuccessful()->expectsOutputToContain('The period digest was sent.');
    test()->artisan('moashir:assessments:remind-paid-drafts')->assertSuccessful()->expectsOutputToContain('Reminded 0 paid drafts.');
    expect(periodByKey('2027-Q1')->last_digest_on?->toDateString())->toBe('2027-02-01');

    $events = collect(app(Schedule::class)->events())->filter(static fn (Event $event): bool => str_contains((string) $event->command, 'moashir:periods:') || str_contains((string) $event->command, 'remind-paid-drafts'));

    expect($events)->toHaveCount(5)
        ->and($events->every(static fn (Event $event): bool => $event->withoutOverlapping && $event->timezone === 'Asia/Riyadh'))->toBeTrue();
});
