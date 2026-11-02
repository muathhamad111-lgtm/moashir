<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Notifications\NotificationCode;
use App\Models\Assessments\Assessment;
use App\Models\Audit\Audit;
use App\Models\Award\AwardCycle;
use App\Models\Award\CommitteeDiscussionEntry;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Identity\User;
use App\Notifications\Entities\EntityNotification;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

const DISCUSSION_OPINION = 'أرى أن الجهة تستحق تقديرًا أعلى في معيار النتائج بناءً على الشواهد المرفقة.';

const AGREED_OUTCOME = 'اتفقت اللجنة على اعتماد المتوسط كما هو مع التوصية بتحسين توثيق الشواهد.';

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-01 08:00:00');
});

/**
 * @return list<string>
 */
function discussionNoticeCodes(User $user): array
{
    return array_values(array_map(
        static fn (EntityNotification $notification): string => $notification->notice->code->value,
        Notification::sent($user, EntityNotification::class)->all(),
    ));
}

/**
 * @return array{cycle: AwardCycle, version: CriteriaVersion, secretary: User, officer: User, assessors: list<User>, requests: list<Assessment>}
 */
function awaitingApprovalScenario(): array
{
    $scenario = committeeScenario(1, 2);

    foreach ($scenario['assessors'] as $assessor) {
        submitCommitteeEvaluation($assessor, $scenario['requests'][0], $scenario['version'])->assertOk();
    }

    return $scenario;
}

it('opens a discussion session with a deadline where every member writes an opinion', function (): void {
    $scenario = awaitingApprovalScenario();
    $assessment = $scenario['requests'][0];
    [$first, $second] = $scenario['assessors'];
    $path = secretaryPath($scenario['cycle'], $assessment, 'discussions');

    switchTo($scenario['secretary'])->postJson($path, ['mode' => 'session'])->assertUnprocessable();
    $id = test()->postJson($path, ['mode' => 'session', 'deadline_at' => '2026-11-05T08:00:00Z'])->assertCreated()
        ->assertJsonPath('data.mode', 'session')
        ->assertJsonPath('data.status', 'open')
        ->json('data.id');
    test()->postJson($path, ['mode' => 'session', 'deadline_at' => '2026-11-05T08:00:00Z'])->assertConflict();
    expect($assessment->refresh()->status->value)->toBe('committee_discussion')
        ->and(discussionNoticeCodes($first))->toContain(NotificationCode::CommitteeDiscussionOpened->value);

    switchTo($first)->postJson("/api/v1/committee/me/discussions/{$id}/entries", ['kind' => 'approve'])->assertUnprocessable()->assertJsonPath('code', 'discussion.entry_kind_invalid');
    test()->postJson("/api/v1/committee/me/discussions/{$id}/entries", ['kind' => 'opinion'])->assertUnprocessable();
    test()->postJson("/api/v1/committee/me/discussions/{$id}/entries", ['kind' => 'opinion', 'body' => DISCUSSION_OPINION])->assertCreated();
    test()->postJson("/api/v1/committee/me/discussions/{$id}/entries", ['kind' => 'opinion', 'body' => DISCUSSION_OPINION.' مع إضافة.'])->assertCreated();
    expect(CommitteeDiscussionEntry::query()->count())->toBe(2)
        ->and(CommitteeDiscussionEntry::query()->whereNull('superseded_at')->count())->toBe(1)
        ->and(discussionNoticeCodes($scenario['secretary']))->toContain(NotificationCode::CommitteeDiscussionEntry->value);

    switchTo($second)->getJson('/api/v1/committee/me/discussions')->assertOk()
        ->assertJsonPath('data.0.id', $id)
        ->assertJsonPath('data.0.entries.0.is_mine', false)
        ->assertJsonPath('data.0.my_entry', null)
        ->assertJsonMissing(['name' => $first->name]);
    switchTo(assessorUser())->postJson("/api/v1/committee/me/discussions/{$id}/entries", ['kind' => 'opinion', 'body' => DISCUSSION_OPINION])->assertNotFound();
    switchTo(ownerOf($assessment->entity))->getJson("/api/v1/assessments/{$assessment->id}")->assertOk()->assertJsonPath('data.status', 'under_review');

    Date::setTestNow('2026-11-06 08:00:00');
    switchTo($second)->postJson("/api/v1/committee/me/discussions/{$id}/entries", ['kind' => 'opinion', 'body' => DISCUSSION_OPINION])->assertConflict()->assertJsonPath('code', 'discussion.deadline_passed');

    switchTo($scenario['secretary'])->postJson(secretaryPath($scenario['cycle'], $assessment, "discussions/{$id}/close"), ['summary' => 'راجعت الأمانة الآراء.'])->assertOk()->assertJsonPath('data.status', 'closed');
    expect($assessment->refresh()->status->value)->toBe('awaiting_secretary_approval')
        ->and(Audit::query()->where('event', AuditEvent::CommitteeDiscussionClosed->value)->count())->toBe(1);
    test()->getJson(secretaryPath($scenario['cycle'], $assessment))->assertOk()->assertJsonPath('data.discussions.0.entries.0.member.name', $first->name);
});

it('lets members approve an agreed outcome or request an edit with a reason', function (): void {
    $scenario = awaitingApprovalScenario();
    $assessment = $scenario['requests'][0];
    [$first, $second] = $scenario['assessors'];

    $id = switchTo($scenario['secretary'])->postJson(secretaryPath($scenario['cycle'], $assessment, 'discussions'), ['mode' => 'agreed_outcome', 'agreed_outcome' => AGREED_OUTCOME])->assertCreated()
        ->assertJsonPath('data.agreed_outcome', AGREED_OUTCOME)
        ->assertJsonPath('data.deadline_at', null)
        ->json('data.id');

    switchTo($first)->postJson("/api/v1/committee/me/discussions/{$id}/entries", ['kind' => 'approve'])->assertCreated();
    switchTo($second)->postJson("/api/v1/committee/me/discussions/{$id}/entries", ['kind' => 'request_edit'])->assertUnprocessable();
    test()->postJson("/api/v1/committee/me/discussions/{$id}/entries", ['kind' => 'request_edit', 'body' => DISCUSSION_OPINION])->assertCreated();

    switchTo($scenario['secretary'])->postJson(secretaryPath($scenario['cycle'], $assessment, 'return'), ['general_note' => COMMITTEE_RETURN_NOTE])->assertOk()->assertJsonPath('data.status', 'returned_to_committee')->assertJsonPath('data.discussions.0.status', 'cancelled');
    switchTo($first)->postJson("/api/v1/committee/me/discussions/{$id}/entries", ['kind' => 'approve'])->assertConflict()->assertJsonPath('code', 'discussion.closed');
});

it('opens discussions only for the secretary of the cycle and only while awaiting approval', function (): void {
    $scenario = committeeScenario(1, 1);
    $assessment = $scenario['requests'][0];
    $path = secretaryPath($scenario['cycle'], $assessment, 'discussions');

    switchTo($scenario['secretary'])->postJson($path, ['mode' => 'agreed_outcome', 'agreed_outcome' => AGREED_OUTCOME])->assertConflict();
    switchTo(systemAdmin())->postJson($path, ['mode' => 'agreed_outcome', 'agreed_outcome' => AGREED_OUTCOME])->assertForbidden();
    switchTo($scenario['assessors'][0])->postJson($path, ['mode' => 'agreed_outcome', 'agreed_outcome' => AGREED_OUTCOME])->assertForbidden();
});
