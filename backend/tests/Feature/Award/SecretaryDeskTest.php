<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Award\SecretaryDecisionKind;
use App\Enums\Notifications\NotificationCode;
use App\Models\Assessments\Assessment;
use App\Models\Audit\Audit;
use App\Models\Award\AwardCycle;
use App\Models\Award\AwardRanking;
use App\Models\Award\CommitteeAverage;
use App\Models\Award\SecretaryDecision;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Identity\User;
use App\Notifications\Entities\EntityNotification;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-01 08:00:00');
});

/**
 * @return list<string>
 */
function deskNoticeCodes(User $user): array
{
    return array_values(array_map(
        static fn (EntityNotification $notification): string => $notification->notice->code->value,
        Notification::sent($user, EntityNotification::class)->all(),
    ));
}

/**
 * @return array{cycle: AwardCycle, version: CriteriaVersion, secretary: User, officer: User, assessors: list<User>, requests: list<Assessment>}
 */
function submittedCommitteeScenario(): array
{
    $scenario = committeeScenario(1, 2);
    [$first, $second] = $scenario['assessors'];
    submitCommitteeEvaluation($first, $scenario['requests'][0], $scenario['version'])->assertOk();
    submitCommitteeEvaluation($second, $scenario['requests'][0], $scenario['version'], COMMITTEE_TOP_MARKS)->assertOk();

    return $scenario;
}

it('lists the committee requests with the initial result, every evaluation and the average for the secretary', function (): void {
    $scenario = submittedCommitteeScenario();
    $cycle = $scenario['cycle'];
    $assessment = $scenario['requests'][0];
    [$first] = $scenario['assessors'];

    switchTo($scenario['secretary'])->getJson(secretaryPath($cycle))->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('meta.counts.awaiting_secretary_approval', 1)
        ->assertJsonPath('data.0.average.percent', '88.50')
        ->assertJsonPath('data.0.initial_percent', '77.00')
        ->assertJsonPath('data.0.committee.completed', 2);
    test()->getJson(secretaryPath($cycle).'?filter[status]=approved')->assertOk()->assertJsonPath('meta.pagination.total', 0);
    test()->getJson(secretaryPath($cycle).'?filter[status]=draft')->assertUnprocessable();

    $detail = test()->getJson(secretaryPath($cycle, $assessment))->assertOk()
        ->assertJsonPath('data.all_submitted', true)
        ->assertJsonPath('data.initial_result.percent', '77.00')
        ->assertJsonPath('data.evaluations.0.member.user.id', $first->id)
        ->assertJsonPath('data.evaluations.0.counted', true);
    $evaluationId = $detail->json('data.evaluations.0.id');
    test()->getJson(secretaryPath($cycle, $assessment, "evaluations/{$evaluationId}"))->assertOk()
        ->assertJsonPath('data.result.percent', '77.00')
        ->assertJsonPath('data.member.user.name', $first->name)
        ->assertJsonStructure(['data' => ['scores', 'notes', 'result' => ['main', 'sub', 'points']]]);

    switchTo(systemAdmin())->getJson(secretaryPath($cycle, $assessment))->assertOk();
    switchTo($scenario['officer'])->getJson(secretaryPath($cycle))->assertForbidden();
    switchTo(awardSecretary())->getJson(secretaryPath($cycle))->assertNotFound();
    switchTo($first)->getJson(secretaryPath($cycle))->assertForbidden();
    switchTo(ownerOf($assessment->entity))->getJson(secretaryPath($cycle))->assertForbidden();
});

it('recomputes the committee average idempotently', function (): void {
    $scenario = submittedCommitteeScenario();
    $assessment = $scenario['requests'][0];

    switchTo($scenario['secretary'])->postJson(secretaryPath($scenario['cycle'], $assessment, 'average'))->assertOk()
        ->assertJsonPath('meta.recomputed', false)
        ->assertJsonPath('data.average.version', 1);
    test()->postJson(secretaryPath($scenario['cycle'], $assessment, 'average'))->assertOk()->assertJsonPath('data.average.version', 1);

    expect(CommitteeAverage::query()->where('assessment_id', $assessment->id)->count())->toBe(1)
        ->and(Audit::query()->where('event', AuditEvent::AwardAverageComputed->value)->count())->toBe(3);
    switchTo(systemAdmin())->postJson(secretaryPath($scenario['cycle'], $assessment, 'average'))->assertForbidden();
});

it('refuses to recompute or approve while a committee assessor has not submitted', function (): void {
    $scenario = committeeScenario(1, 2);
    $assessment = $scenario['requests'][0];
    submitCommitteeEvaluation($scenario['assessors'][0], $assessment, $scenario['version'])->assertOk();

    switchTo($scenario['secretary'])->postJson(secretaryPath($scenario['cycle'], $assessment, 'average'))->assertConflict()->assertJsonPath('code', 'state.transition_illegal');
    test()->postJson(secretaryPath($scenario['cycle'], $assessment, 'approve'))->assertConflict()->assertJsonPath('code', 'state.transition_illegal');
});

it('returns a request to every committee assessor with a general note as often as needed', function (): void {
    $scenario = submittedCommitteeScenario();
    $assessment = $scenario['requests'][0];
    $cycle = $scenario['cycle'];
    [$first, $second] = $scenario['assessors'];

    switchTo($scenario['secretary'])->postJson(secretaryPath($cycle, $assessment, 'return'))->assertUnprocessable();
    test()->postJson(secretaryPath($cycle, $assessment, 'return'), ['general_note' => COMMITTEE_RETURN_NOTE])->assertOk()
        ->assertJsonPath('data.status', 'returned_to_committee')
        ->assertJsonPath('data.average', null)
        ->assertJsonPath('data.evaluations.0.status', 'in_progress');
    expect(deskNoticeCodes($first))->toContain(NotificationCode::ReturnedToCommittee->value)
        ->and($cycle->refresh()->status->value)->toBe('committee_assessment');

    switchTo($first)->getJson(myAssignmentPath(committeeRowOf($first, $assessment)))->assertOk()
        ->assertJsonPath('data.mode', 'edit')
        ->assertJsonPath('data.return_note.note', COMMITTEE_RETURN_NOTE)
        ->assertJsonPath('data.progress.scored', 4);
    submitCommitteeEvaluation($first, $assessment, $scenario['version'], COMMITTEE_MID_MARKS)->assertOk();
    expect($assessment->refresh()->status->value)->toBe('under_committee_assessment');
    submitCommitteeEvaluation($second, $assessment, $scenario['version'], COMMITTEE_TOP_MARKS)->assertOk();
    expect($assessment->refresh()->status->value)->toBe('awaiting_secretary_approval');

    switchTo($scenario['secretary'])->postJson(secretaryPath($cycle, $assessment, 'return'), ['general_note' => COMMITTEE_RETURN_NOTE])->assertOk();

    expect(SecretaryDecision::query()->where('assessment_id', $assessment->id)->where('kind', SecretaryDecisionKind::ReturnToCommittee)->count())->toBe(2)
        ->and(CommitteeAverage::query()->where('assessment_id', $assessment->id)->count())->toBe(2);
});

it('approves the committee average, records the ranking row and tells the admins and the committee', function (): void {
    $scenario = submittedCommitteeScenario();
    $assessment = $scenario['requests'][0];
    $admin = systemAdmin();

    switchTo(systemAdmin())->postJson(secretaryPath($scenario['cycle'], $assessment, 'approve'))->assertForbidden();
    switchTo($scenario['secretary'])->postJson(secretaryPath($scenario['cycle'], $assessment, 'approve'), ['note' => 'اعتماد بعد المراجعة.'])->assertOk()->assertJsonPath('data.status', 'approved');
    test()->postJson(secretaryPath($scenario['cycle'], $assessment, 'return'), ['general_note' => COMMITTEE_RETURN_NOTE])->assertConflict();

    expect(AwardRanking::query()->where('assessment_id', $assessment->id)->sole()->average_percent->toDisplay())->toBe('88.50')
        ->and(deskNoticeCodes($admin))->toContain(NotificationCode::CommitteeAverageApproved->value)
        ->and(deskNoticeCodes($scenario['assessors'][0]))->toContain(NotificationCode::CommitteeAverageApproved->value)
        ->and(deskNoticeCodes(ownerOf($assessment->entity)))->toBe([NotificationCode::AdvancedSubmitted->value])
        ->and(Audit::query()->where('event', AuditEvent::AwardAverageApproved->value)->count())->toBe(1);
    switchTo(ownerOf($assessment->entity))->getJson("/api/v1/assessments/{$assessment->id}")->assertOk()->assertJsonPath('data.status', 'under_review');
    switchTo(ownerOf($assessment->entity))->getJson("/api/v1/assessments/{$assessment->id}/result")->assertOk()->assertJsonPath('data.available', false);
});
