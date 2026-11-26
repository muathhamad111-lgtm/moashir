<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Award\AwardCycleStatus;
use App\Enums\Award\CycleMemberRole;
use App\Enums\Notifications\NotificationCode;
use App\Enums\Settings\SettingKey;
use App\Models\Assessments\Assessment;
use App\Models\Audit\Audit;
use App\Models\Award\AwardCycle;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Entities\Region;
use App\Models\Identity\User;
use App\Notifications\Entities\EntityNotification;
use App\Services\Award\RunningCycleGuard;
use App\Services\Settings\SettingsStore;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

const CONCURRENCY_REASON = 'قرار اللجنة العليا للجائزة بتشغيل أكثر من دورة في الفترة نفسها.';

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-01 08:00:00');
});

function useCycleConcurrency(string $mode): void
{
    app(SettingsStore::class)->put(SettingKey::AwardCycleConcurrency, $mode, AuditEvent::AssessmentSettingsUpdated, systemAdmin());
}

/**
 * @param  array<string, mixed>  $attributes
 */
function assessedDraft(CriteriaVersion $version, array $attributes = [], ?Region $region = null): AwardCycle
{
    $cycle = draftAwardCycle($version, $attributes, $region);
    linkInitialAssessor($cycle, assessorUser());

    return $cycle;
}

function committeeStageCycle(CriteriaVersion $version): AwardCycle
{
    $now = Date::now();

    return openAwardCycle($version, [
        'status' => AwardCycleStatus::CommitteeAssessment,
        'submission_start' => $now->copy()->subDays(30),
        'submission_end' => $now->copy()->subDays(10),
        'submissions_closed_at' => $now->copy()->subDays(10),
        'committee_start' => $now->copy()->subDays(5),
        'committee_end' => $now->copy()->addDays(20),
        'announcement_at' => $now->copy()->addDays(30),
    ]);
}

function cycleNoticeCount(User $user, NotificationCode $code): int
{
    return Notification::sent($user, EntityNotification::class)
        ->filter(static fn (EntityNotification $notification): bool => $notification->notice->code === $code)
        ->count();
}

it('reads one running cycle as the default and lets an assessment-settings manager change it with a reason', function (): void {
    $administrator = systemAdmin();

    switchTo($administrator)->getJson('/api/v1/settings/assessment')->assertOk()->assertJsonPath('data.advanced_cycle_concurrency', 'single');

    test()->putJson('/api/v1/settings/assessment', ['advanced_cycle_concurrency' => 'concurrent'])->assertUnprocessable()
        ->assertJsonPath('code', 'reason_required');
    test()->putJson('/api/v1/settings/assessment', ['advanced_cycle_concurrency' => 'concurrent', 'reason' => CONCURRENCY_REASON])->assertOk()
        ->assertJsonPath('data.advanced_cycle_concurrency', 'concurrent');

    $audit = Audit::query()->where('event', AuditEvent::AssessmentSettingsUpdated->value)->latest('id')->firstOrFail();

    expect($audit->reason)->toBe(CONCURRENCY_REASON);
    test()->putJson('/api/v1/settings/assessment', ['advanced_cycle_concurrency' => 'concurrent'])->assertOk();
});

it('validates the cycle concurrency and keeps it away from roles without assessment settings', function (): void {
    switchTo(systemAdmin())->putJson('/api/v1/settings/assessment', ['advanced_cycle_concurrency' => 'unlimited', 'reason' => CONCURRENCY_REASON])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('advanced_cycle_concurrency');
    switchTo(awardSecretary())->putJson('/api/v1/settings/assessment', ['advanced_cycle_concurrency' => 'concurrent', 'reason' => CONCURRENCY_REASON])->assertForbidden();
    test()->getJson('/api/v1/settings/assessment')->assertForbidden();
});

it('blocks any second running cycle in single mode, even one already in committee', function (): void {
    $version = publishedAccreditedCriteria();
    $committee = committeeStageCycle($version);
    $draft = assessedDraft($version);

    switchTo(systemAdmin())->getJson("/api/v1/award/cycles/{$draft->id}")->assertOk()
        ->assertJsonPath('data.open_block.code', 'cycle.another_open')
        ->assertJsonPath('data.open_block.cycle.id', $committee->id);
    test()->postJson("/api/v1/award/cycles/{$draft->id}/open")->assertConflict()
        ->assertJsonPath('code', 'cycle.another_open')
        ->assertJsonPath('meta.award_cycle_id', $committee->id)
        ->assertJsonPath('meta.concurrency', 'single');

    expect($draft->refresh()->status)->toBe(AwardCycleStatus::Draft);
});

it('opens a new cycle in staggered mode while another is in committee but not while another accepts submissions', function (): void {
    useCycleConcurrency('staggered');
    $version = publishedAccreditedCriteria();
    committeeStageCycle($version);
    $first = assessedDraft($version);
    $second = assessedDraft($version);

    switchTo(systemAdmin())->getJson("/api/v1/award/cycles/{$first->id}")->assertOk()->assertJsonPath('data.open_block', null);
    test()->postJson("/api/v1/award/cycles/{$first->id}/open")->assertOk()->assertJsonPath('data.status', 'submission_open');

    test()->getJson("/api/v1/award/cycles/{$second->id}")->assertOk()
        ->assertJsonPath('data.open_block.code', 'cycle.another_open_for_submission')
        ->assertJsonPath('data.open_block.cycle.id', $first->id);
    test()->postJson("/api/v1/award/cycles/{$second->id}/open")->assertConflict()
        ->assertJsonPath('code', 'cycle.another_open_for_submission')
        ->assertJsonPath('meta.award_cycle_id', $first->id);

    expect(AwardCycle::query()->running()->count())->toBe(2)
        ->and($second->refresh()->status)->toBe(AwardCycleStatus::Draft);
});

it('opens several cycles for submission at the same time in concurrent mode', function (): void {
    useCycleConcurrency('concurrent');
    $version = publishedAccreditedCriteria();
    $running = openAwardCycle($version);
    $draft = assessedDraft($version);

    switchTo(systemAdmin())->getJson("/api/v1/award/cycles/{$draft->id}")->assertOk()->assertJsonPath('data.open_block', null);
    test()->postJson("/api/v1/award/cycles/{$draft->id}/open")->assertOk()->assertJsonPath('data.status', 'submission_open');

    expect(AwardCycle::query()->where('status', AwardCycleStatus::SubmissionOpen)->pluck('id')->all())->toEqualCanonicalizing([$running->id, $draft->id]);
});

it('reopens a closed cycle next to a running one only when the setting allows it', function (): void {
    $version = publishedAccreditedCriteria();
    $closed = draftAwardCycle($version, [
        'status' => AwardCycleStatus::Closed, 'closed_at' => Date::now(), 'closed_from_status' => AwardCycleStatus::Announced,
        'announced_at' => Date::now(), 'national_approved_at' => Date::now(),
    ]);
    openAwardCycle($version);

    switchTo(superAdmin())->postJson("/api/v1/award/cycles/{$closed->id}/reopen", ['reason' => SUPER_ADMIN_REASON])->assertConflict()->assertJsonPath('code', 'cycle.another_open');

    useCycleConcurrency('staggered');
    test()->postJson("/api/v1/award/cycles/{$closed->id}/reopen", ['reason' => SUPER_ADMIN_REASON])->assertOk()->assertJsonPath('data.status', 'announced');
});

it('serializes cycle starts under one transaction lock so racing opens leave one running cycle in single mode', function (): void {
    $version = publishedAccreditedCriteria();
    $first = assessedDraft($version);
    $second = assessedDraft($version);
    config()->set('database.connections.race_probe', config('database.connections.'.config('database.default')));

    // A second session cannot take the start lock while the first start is in flight, so a racing open waits for the
    // first commit and then re-checks the running cycles; the sequential re-check below is what that waiting open sees.
    DB::transaction(function () use ($first): void {
        app(RunningCycleGuard::class)->assertMayStart($first, AwardCycleStatus::SubmissionOpen);
        $probe = DB::connection('race_probe')->selectOne('select pg_try_advisory_lock(hashtext(?)) as taken', [RunningCycleGuard::LOCK]);

        expect($probe?->taken)->toBeFalse();
    });
    DB::purge('race_probe');

    switchTo(systemAdmin())->postJson("/api/v1/award/cycles/{$first->id}/open")->assertOk();
    test()->postJson("/api/v1/award/cycles/{$second->id}/open")->assertConflict()->assertJsonPath('code', 'cycle.another_open');

    expect(AwardCycle::query()->running()->pluck('id')->all())->toBe([$first->id]);
});

it('lists the cycles open for submission publicly, newest first', function (): void {
    useCycleConcurrency('concurrent');
    $version = publishedAccreditedCriteria();

    test()->getJson('/api/v1/award/cycles/open')->assertOk()->assertJsonPath('data', []);

    $older = openAwardCycle($version, ['submission_start' => Date::now()->subDays(3)]);
    $newer = openAwardCycle($version);
    committeeStageCycle($version);
    assessedDraft($version);

    test()->getJson('/api/v1/award/cycles/open')->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.1.id', $older->id)
        ->assertJsonPath('data.0.is_accepting_applications', true)
        ->assertJsonMissingPath('data.0.disqualification_score');
    test()->getJson('/api/v1/award/cycles/current')->assertOk()->assertJsonPath('data.id', $newer->id);
});

it('shows the entity every open cycle with its own eligibility and request', function (): void {
    useCycleConcurrency('concurrent');
    $version = publishedAccreditedCriteria();
    $first = openAwardCycle($version, ['submission_start' => Date::now()->subDays(2)]);
    $entity = cycleEntity($first, 2020);
    $second = openAwardCycle($version, ['min_entity_age_years' => 10], $first->regions->firstOrFail());
    $draft = advancedDraft($first, $entity);
    $owner = ownerOf($entity);

    $award = boardWidget(boardOf($owner, 'entity'), 'paths')['award'];

    expect(array_column($award['open_cycles'], 'id'))->toBe([$second->id, $first->id])
        ->and($award['open_cycles'][0]['eligibility'])->toBe(['eligible' => false, 'reason' => 'min_entity_age'])
        ->and($award['open_cycles'][0]['request'])->toBeNull()
        ->and($award['open_cycles'][0]['entry_fee']['total'])->toBe('0.00')
        ->and($award['open_cycles'][1]['eligibility'])->toBe(['eligible' => false, 'reason' => 'request_exists'])
        ->and($award['open_cycles'][1]['request']['id'])->toBe($draft->id);

    switchTo($owner)->getJson('/api/v1/assessments/advanced/current')->assertOk()
        ->assertJsonPath('data.cycle.id', $second->id)
        ->assertJsonCount(2, 'data.open_cycles');
    test()->getJson("/api/v1/assessments/advanced/current?award_cycle_id={$first->id}")->assertOk()
        ->assertJsonPath('data.cycle.id', $first->id)
        ->assertJsonPath('data.request.id', $draft->id);
    test()->getJson('/api/v1/assessments/advanced/current?award_cycle_id=abc')->assertUnprocessable()->assertJsonValidationErrors('award_cycle_id');
});

it('invites the entity to an older open cycle when it does not meet the conditions of the newest one', function (): void {
    useCycleConcurrency('concurrent');
    $version = publishedAccreditedCriteria();
    $older = openAwardCycle($version, ['submission_start' => Date::now()->subDays(2)]);
    $entity = cycleEntity($older, 2020);
    $newer = openAwardCycle($version, ['min_entity_age_years' => 10], $older->regions->firstOrFail());
    $owner = ownerOf($entity);

    $board = boardOf($owner, 'entity');

    expect(boardWidget($board, 'paths')['award']['cycle']['id'])->toBe($newer->id)
        ->and(boardWidget($board, 'paths')['award']['eligibility']['reason'])->toBe('min_entity_age')
        ->and(boardWidget($board, 'next_step'))->toMatchArray(['key' => 'apply_award', 'path' => 'advanced'])
        ->and(boardWidget($board, 'next_step')['context']['cycle_id'])->toBe($older->id);
});

it('lets one entity apply to two concurrent cycles with one request in each', function (): void {
    useCycleConcurrency('concurrent');
    $version = publishedAccreditedCriteria();
    $first = openAwardCycle($version);
    $entity = cycleEntity($first);
    $second = openAwardCycle($version, [], $first->regions->firstOrFail());
    $owner = ownerOf($entity);

    $inFirst = applyForCycle($owner, $first)->assertCreated()->assertJsonPath('data.award_cycle_id', $first->id)->json('data.id');
    $inSecond = applyForCycle($owner, $second)->assertCreated()->assertJsonPath('data.award_cycle_id', $second->id)->json('data.id');
    applyForCycle($owner, $second)->assertOk()->assertJsonPath('data.id', $inSecond);

    expect($inFirst)->not->toBe($inSecond)
        ->and(Assessment::query()->where('entity_id', $entity->id)->count())->toBe(2);
});

it('applies a switch back to single to future opens only', function (): void {
    useCycleConcurrency('concurrent');
    $version = publishedAccreditedCriteria();
    $running = openAwardCycle($version);
    $draft = assessedDraft($version);

    switchTo(systemAdmin())->putJson('/api/v1/settings/assessment', ['advanced_cycle_concurrency' => 'single', 'reason' => CONCURRENCY_REASON])->assertOk()
        ->assertJsonPath('data.advanced_cycle_concurrency', 'single');

    expect($running->refresh()->status)->toBe(AwardCycleStatus::SubmissionOpen);
    test()->postJson("/api/v1/award/cycles/{$draft->id}/open")->assertConflict()->assertJsonPath('code', 'cycle.another_open');
});

function closeCycleCompletely(AwardCycle $cycle): void
{
    $cycle->forceFill(['status' => AwardCycleStatus::Closed, 'closed_at' => Date::now(), 'closed_from_status' => AwardCycleStatus::SubmissionClosed])->save();
}

it('accepts a switch to single while two cycles run and applies it to future openings only', function (): void {
    useCycleConcurrency('concurrent');
    $version = publishedAccreditedCriteria();
    $first = openAwardCycle($version);
    $second = committeeStageCycle($version);
    $due = assessedDraft($version, ['submission_start' => '2026-11-01 07:00:00', 'submission_end' => '2026-11-20 07:00:00']);

    switchTo(systemAdmin())->putJson('/api/v1/settings/assessment', ['advanced_cycle_concurrency' => 'single', 'reason' => CONCURRENCY_REASON])->assertOk()
        ->assertJsonPath('data.advanced_cycle_concurrency', 'single')
        ->assertJsonPath('data.running_cycles', ['running' => 2, 'open_for_submission' => 1]);

    expect(Audit::query()->where('event', AuditEvent::AssessmentSettingsUpdated->value)->latest('id')->firstOrFail()->reason)->toBe(CONCURRENCY_REASON)
        ->and($first->refresh()->status)->toBe(AwardCycleStatus::SubmissionOpen)
        ->and($second->refresh()->status)->toBe(AwardCycleStatus::CommitteeAssessment);

    test()->postJson("/api/v1/award/cycles/{$due->id}/open")->assertConflict()->assertJsonPath('code', 'cycle.another_open');
    Artisan::call('moashir:award:advance-cycles');

    expect($due->refresh()->status)->toBe(AwardCycleStatus::Draft)
        ->and(Audit::query()->where('event', AuditEvent::CycleOpenDeferred->value)->count())->toBe(1);

    closeCycleCompletely($first);
    closeCycleCompletely($second);
    test()->postJson("/api/v1/award/cycles/{$due->id}/open")->assertOk()->assertJsonPath('data.status', 'submission_open');
});

it('accepts a switch to staggered while two cycles accept submissions and applies it to future openings only', function (): void {
    useCycleConcurrency('concurrent');
    $version = publishedAccreditedCriteria();
    $first = openAwardCycle($version);
    $second = openAwardCycle($version);
    $due = assessedDraft($version, ['submission_start' => '2026-11-01 07:00:00', 'submission_end' => '2026-11-20 07:00:00']);

    switchTo(systemAdmin())->putJson('/api/v1/settings/assessment', ['advanced_cycle_concurrency' => 'staggered', 'reason' => CONCURRENCY_REASON])->assertOk()
        ->assertJsonPath('data.advanced_cycle_concurrency', 'staggered')
        ->assertJsonPath('data.running_cycles', ['running' => 2, 'open_for_submission' => 2]);

    expect(Audit::query()->where('event', AuditEvent::AssessmentSettingsUpdated->value)->latest('id')->firstOrFail()->reason)->toBe(CONCURRENCY_REASON)
        ->and(AwardCycle::query()->where('status', AwardCycleStatus::SubmissionOpen)->count())->toBe(2);

    test()->postJson("/api/v1/award/cycles/{$due->id}/open")->assertConflict()->assertJsonPath('code', 'cycle.another_open_for_submission');
    Artisan::call('moashir:award:advance-cycles');

    expect($due->refresh()->status)->toBe(AwardCycleStatus::Draft)
        ->and(Audit::query()->where('event', AuditEvent::CycleOpenDeferred->value)->count())->toBe(1);

    closeCycleSubmissions($first);
    closeCycleSubmissions($second);
    test()->postJson("/api/v1/award/cycles/{$due->id}/open")->assertOk()->assertJsonPath('data.status', 'submission_open');

    expect(AwardCycle::query()->running()->count())->toBe(3);
});

it('keeps a due cycle as a draft when the setting blocks it and tells the staff once a day', function (): void {
    $version = publishedAccreditedCriteria();
    $running = openAwardCycle($version);
    $due = assessedDraft($version, ['submission_start' => '2026-11-01 07:00:00', 'submission_end' => '2026-11-20 07:00:00']);
    $secretary = awardSecretary();
    addCycleMember($due, $secretary, CycleMemberRole::AwardSecretary);
    $admin = systemAdmin();

    Artisan::call('moashir:award:advance-cycles');
    Artisan::call('moashir:award:advance-cycles');

    expect($due->refresh()->status)->toBe(AwardCycleStatus::Draft)
        ->and(cycleNoticeCount($admin, NotificationCode::CycleOpenDeferred))->toBe(1)
        ->and(cycleNoticeCount($secretary, NotificationCode::CycleOpenDeferred))->toBe(1)
        ->and(Audit::query()->where('event', AuditEvent::CycleOpenDeferred->value)->count())->toBe(1);

    Date::setTestNow('2026-11-02 08:00:00');
    Artisan::call('moashir:award:advance-cycles');

    expect(cycleNoticeCount($secretary, NotificationCode::CycleOpenDeferred))->toBe(2);

    useCycleConcurrency('concurrent');
    Artisan::call('moashir:award:advance-cycles');

    expect($due->refresh()->status)->toBe(AwardCycleStatus::SubmissionOpen)
        ->and($running->refresh()->status)->toBe(AwardCycleStatus::SubmissionOpen);
});

it('opens every due cycle on schedule in concurrent mode', function (): void {
    useCycleConcurrency('concurrent');
    $version = publishedAccreditedCriteria();
    $first = assessedDraft($version, ['submission_start' => '2026-11-01 07:00:00', 'submission_end' => '2026-11-20 07:00:00']);
    $second = assessedDraft($version, ['submission_start' => '2026-11-01 07:30:00', 'submission_end' => '2026-11-20 07:00:00']);

    Artisan::call('moashir:award:advance-cycles');

    expect($first->refresh()->status)->toBe(AwardCycleStatus::SubmissionOpen)
        ->and($second->refresh()->status)->toBe(AwardCycleStatus::SubmissionOpen);
});

it('counts and lists every running cycle on the staff surfaces in concurrent mode', function (): void {
    useCycleConcurrency('concurrent');
    $version = publishedAccreditedCriteria();
    $older = openAwardCycle($version, ['submission_start' => Date::now()->subDays(3)]);
    $newer = openAwardCycle($version);
    $nextDraftId = switchTo(criteriaOfficer())->postJson('/api/v1/criteria/versions', [])->assertCreated()->json('data.id');

    test()->getJson("/api/v1/criteria/versions/{$nextDraftId}/check")->assertOk()
        ->assertJsonPath('meta.award_cycles.running.id', $newer->id)
        ->assertJsonPath('meta.award_cycles.running_cycles.0.id', $newer->id)
        ->assertJsonPath('meta.award_cycles.running_cycles.1.id', $older->id);

    expect(boardCards(boardOf(systemAdmin(), 'system_admin'))->get('award_cycles_running'))->toBe(2);
});

it('links the cycle-opened notice of each cycle to that cycle on the entity award path', function (): void {
    useCycleConcurrency('concurrent');
    $version = publishedAccreditedCriteria();
    $running = openAwardCycle($version);
    $draft = assessedDraft($version, [], $running->regions->firstOrFail());
    $entity = cycleEntity($draft);

    switchTo(systemAdmin())->postJson("/api/v1/award/cycles/{$draft->id}/open")->assertOk();

    Notification::assertSentTo(ownerOf($entity), EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::AdvancedCycleOpened
        && $notification->notice->link === '/paths/award?cycle='.$draft->id);
});

it('checks the initial assessors before the running cycles, so a cycle that could never open says so first', function (): void {
    $version = publishedAccreditedCriteria();
    openAwardCycle($version);
    $bare = draftAwardCycle($version, ['submission_start' => '2026-11-01 07:00:00', 'submission_end' => '2026-11-20 07:00:00']);
    $admin = systemAdmin();

    switchTo($admin)->postJson("/api/v1/award/cycles/{$bare->id}/open")->assertUnprocessable()->assertJsonPath('code', 'cycle.no_initial_assessors');
    Artisan::call('moashir:award:advance-cycles');

    expect($bare->refresh()->status)->toBe(AwardCycleStatus::Draft)
        ->and(cycleNoticeCount($admin, NotificationCode::CycleOpenBlocked))->toBe(1)
        ->and(cycleNoticeCount($admin, NotificationCode::CycleOpenDeferred))->toBe(0);

    linkInitialAssessor($bare, assessorUser());
    switchTo($admin)->postJson("/api/v1/award/cycles/{$bare->id}/open")->assertConflict()->assertJsonPath('code', 'cycle.another_open');
});
