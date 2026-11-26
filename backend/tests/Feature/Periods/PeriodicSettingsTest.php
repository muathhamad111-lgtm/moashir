<?php

declare(strict_types=1);

use App\Actions\Periods\SendPeriodNotices;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Models\Assessments\AssessmentPeriod;
use App\Models\Assessments\PeriodNotice;
use App\Models\Audit\Audit;
use App\Models\Entities\Entity;
use Illuminate\Support\Facades\Notification;

const PERIODIC_SETTINGS = '/api/v1/settings/assessment/periodic';

const PERIODIC_REASON = 'تعديل دورية التقييم بقرار من اللجنة التنفيذية بعد مراجعة خطة العام.';

beforeEach(function (): void {
    Notification::fake();
});

it('shows the periodic settings card with the current and upcoming periods', function (): void {
    atRiyadh('2027-02-10 10:00');
    livePeriods();

    switchTo(systemAdmin())->getJson(PERIODIC_SETTINGS)->assertOk()
        ->assertJsonPath('data.cadence', 'quarterly')
        ->assertJsonPath('data.notice_days_before', 14)
        ->assertJsonPath('data.reminder_days_before_end', [14, 7, 1])
        ->assertJsonPath('data.audience', 'active_verified_entities')
        ->assertJsonPath('data.certificate_validity', 'next_period_end')
        ->assertJsonPath('data.paid_draft_reminder_days', 7)
        ->assertJsonPath('data.rollout_from', '2027-01-01')
        ->assertJsonPath('data.cadence_effective_from', null)
        ->assertJsonPath('data.current_period.key', '2027-Q1')
        ->assertJsonPath('data.current_period.label.ar', 'الربع الأول 2027')
        ->assertJsonPath('data.upcoming_periods.0.key', '2027-Q2')
        ->assertJsonPath('data.upcoming_periods.0.notice_on', '2027-03-18')
        ->assertJsonCount(4, 'data.upcoming_periods')
        ->assertJsonPath('data.defaults.annual.reminder_days_before_end', [60, 30, 14, 7, 1])
        ->assertJsonPath('data.limits.days_max.quarterly', 89);
});

it('denies the periodic settings to staff without the assessment settings permission and to entities', function (string $method): void {
    foreach ([staffUser(RoleKey::FinanceOfficer), staffUser(RoleKey::Assessor), entityUser()] as $user) {
        switchTo($user)->json($method, PERIODIC_SETTINGS, ['cadence' => 'annual'])->assertForbidden();
    }

    expect(Audit::query()->where('event', AuditEvent::PeriodicSettingsUpdated->value)->whereNotNull('user_id')->exists())->toBeFalse();
})->with(['GET', 'PUT']);

it('rejects the periodic settings for a guest', function (): void {
    test()->getJson(PERIODIC_SETTINGS)->assertUnauthorized();
});

it('changes the cadence at the end of the current period and resets the days to the new defaults', function (): void {
    atRiyadh('2027-02-10 10:00');
    livePeriods();
    $currentId = periodByKey('2027-Q1')->id;

    switchTo(systemAdmin())->putJson(PERIODIC_SETTINGS, ['cadence' => 'semi_annual'])->assertOk()
        ->assertJsonPath('data.cadence', 'semi_annual')
        ->assertJsonPath('data.notice_days_before', 21)
        ->assertJsonPath('data.reminder_days_before_end', [30, 14, 7, 1])
        ->assertJsonPath('data.cadence_effective_from', '2027-04-01')
        ->assertJsonPath('data.current_period.key', '2027-Q1')
        ->assertJsonPath('data.upcoming_periods.0.key', '2027-H1')
        ->assertJsonPath('data.upcoming_periods.0.starts_on', '2027-04-01')
        ->assertJsonPath('data.upcoming_periods.0.ends_on', '2027-06-30')
        ->assertJsonPath('data.upcoming_periods.0.is_shortened', true);

    expect(periodByKey('2027-Q1')->id)->toBe($currentId)
        ->and(AssessmentPeriod::query()->where('key', '2027-Q2')->sole()->superseded_at)->not->toBeNull()
        ->and(Audit::query()->where('event', AuditEvent::PeriodicSettingsUpdated->value)->whereNotNull('user_id')->count())->toBe(3);
});

it('keeps the days that are sent together with a new cadence', function (): void {
    atRiyadh('2027-02-10 10:00');
    livePeriods();

    switchTo(systemAdmin())->putJson(PERIODIC_SETTINGS, ['cadence' => 'annual', 'notice_days_before' => 45, 'reminder_days_before_end' => [1, 90, 30], 'paid_draft_reminder_days' => 10])->assertOk()
        ->assertJsonPath('data.notice_days_before', 45)
        ->assertJsonPath('data.reminder_days_before_end', [90, 30, 1])
        ->assertJsonPath('data.paid_draft_reminder_days', 10);
});

it('validates the periodic settings against the bounds of the cadence', function (array $payload, string $field): void {
    atRiyadh('2027-02-10 10:00');
    livePeriods();

    switchTo(systemAdmin())->putJson(PERIODIC_SETTINGS, $payload)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors([$field]);

    expect(periodByKey('2027-Q2')->superseded_at)->toBeNull();
})->with([
    'unknown cadence' => [['cadence' => 'monthly'], 'cadence'],
    'notice longer than a quarter' => [['notice_days_before' => 90], 'notice_days_before'],
    'notice of zero days' => [['notice_days_before' => 0], 'notice_days_before'],
    'duplicate reminders' => [['reminder_days_before_end' => [7, 7]], 'reminder_days_before_end.1'],
    'too many reminders' => [['reminder_days_before_end' => [60, 50, 40, 30, 20, 10, 1]], 'reminder_days_before_end'],
    'reminder longer than a quarter' => [['reminder_days_before_end' => [120]], 'reminder_days_before_end.0'],
    'unknown audience' => [['audience' => 'all_users'], 'audience'],
    'unknown certificate validity' => [['certificate_validity' => 'one_year'], 'certificate_validity'],
    'paid draft reminder too long' => [['paid_draft_reminder_days' => 61], 'paid_draft_reminder_days'],
    'reason too short' => [['cadence' => 'annual', 'reason' => 'قصير'], 'reason'],
]);

it('accepts the longer annual bounds when the cadence changes in the same request', function (): void {
    atRiyadh('2027-02-10 10:00');
    livePeriods();

    switchTo(systemAdmin())->putJson(PERIODIC_SETTINGS, ['cadence' => 'annual', 'notice_days_before' => 120])->assertOk()
        ->assertJsonPath('data.notice_days_before', 120);
});

it('asks the Super Admin for a reason and records it', function (): void {
    atRiyadh('2027-02-10 10:00');
    livePeriods();
    $superAdmin = superAdmin();

    switchTo($superAdmin)->putJson(PERIODIC_SETTINGS, ['cadence' => 'annual'])->assertUnprocessable()->assertJsonPath('code', 'reason_required');
    expect(periodByKey('2027-Q2')->superseded_at)->toBeNull();

    switchTo($superAdmin)->putJson(PERIODIC_SETTINGS, ['cadence' => 'annual', 'reason' => PERIODIC_REASON])->assertOk();

    expect(Audit::query()->where('event', AuditEvent::PeriodicSettingsUpdated->value)->where('user_id', $superAdmin->id)->pluck('reason')->unique()->all())->toBe([PERIODIC_REASON]);
});

it('previews the impact of a cadence change without saving it', function (): void {
    atRiyadh('2027-03-20 10:00');
    livePeriods();
    app(SendPeriodNotices::class)->handle();
    entityWithOwner();

    switchTo(systemAdmin())->postJson(PERIODIC_SETTINGS.'/preview', ['cadence' => 'semi_annual'])->assertOk()
        ->assertJsonPath('data.current_period.key', '2027-Q1')
        ->assertJsonPath('data.cadence_effective_from', '2027-04-01')
        ->assertJsonPath('data.first_period.key', '2027-H1')
        ->assertJsonPath('data.first_period.id', null)
        ->assertJsonPath('data.first_period.is_shortened', true)
        ->assertJsonPath('data.first_period.notice_on', '2027-03-11')
        ->assertJsonCount(4, 'data.upcoming_periods')
        ->assertJsonCount(4, 'data.removed_periods')
        ->assertJsonPath('data.removed_periods.0.key', '2027-Q2')
        ->assertJsonPath('data.corrections_to_send', 0);

    expect(periodByKey('2027-Q2')->superseded_at)->toBeNull();
});

it('corrects the notice of a removed period with PER-05 once', function (): void {
    atRiyadh('2027-03-20 10:00');
    $entity = entityWithOwner();
    livePeriods();
    app(SendPeriodNotices::class)->handle();

    expect(financeNoticeSent(ownerOf($entity), NotificationCode::PeriodStartingSoon))->toBeTrue();

    switchTo(systemAdmin())->postJson(PERIODIC_SETTINGS.'/preview', ['cadence' => 'annual'])->assertOk()->assertJsonPath('data.corrections_to_send', 1);
    switchTo(systemAdmin())->putJson(PERIODIC_SETTINGS, ['cadence' => 'annual'])->assertOk();

    expect(financeNoticeSent(ownerOf($entity), NotificationCode::PeriodCorrected))->toBeTrue()
        ->and(periodCorrectionsSentTo($entity))->toBe(1);

    generatePeriods();
    expect(periodCorrectionsSentTo($entity))->toBe(1);
});

function periodCorrectionsSentTo(Entity $entity): int
{
    return PeriodNotice::query()->where('entity_id', $entity->id)->where('code', NotificationCode::PeriodCorrected)->count();
}
