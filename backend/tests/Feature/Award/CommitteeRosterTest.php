<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Award\CommitteeMembershipStatus;
use App\Enums\Award\CommitteeVoidReason;
use App\Enums\Award\CycleMemberRole;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Models\Assessments\Assessment;
use App\Models\Audit\Audit;
use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CommitteeAverage;
use App\Models\Award\CycleCommitteeAssessor;
use App\Models\Identity\User;
use App\Notifications\Entities\EntityNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-01 08:00:00');
});

/**
 * @return list<string>
 */
function rosterNoticeCodes(User $user): array
{
    return array_values(array_map(
        static fn (EntityNotification $notification): string => $notification->notice->code->value,
        Notification::sent($user, EntityNotification::class)->all(),
    ));
}

it('appoints active assessors to the committee and lists them for the committee managers', function (): void {
    [$cycle, , $secretary, $officer] = committeeCycle();
    $assessor = assessorUser();

    switchTo($secretary)->postJson(committeePath($cycle, 'members'), ['user_id' => $assessor->id])->assertCreated()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.user.id', $assessor->id)
        ->assertJsonPath('data.is_substitute', false);
    test()->postJson(committeePath($cycle, 'members'), ['user_id' => $assessor->id])->assertUnprocessable()->assertJsonPath('code', 'committee.member_exists');
    test()->postJson(committeePath($cycle, 'members'), ['user_id' => requestFollower()->id])->assertUnprocessable()->assertJsonPath('code', 'committee.candidate_invalid');
    test()->postJson(committeePath($cycle, 'members'), [])->assertUnprocessable();

    foreach ([$secretary, $officer, requestFollower(), systemAdmin()] as $manager) {
        switchTo($manager)->getJson(committeePath($cycle))->assertOk()->assertJsonPath('data.0.user.id', $assessor->id)->assertJsonPath('data.0.progress.not_started', 0);
    }

    switchTo($assessor)->getJson(committeePath($cycle))->assertForbidden();
    switchTo(filteringOfficer())->getJson(committeePath($cycle))->assertNotFound();
    switchTo(filteringOfficer())->postJson(committeePath($cycle, 'members'), ['user_id' => assessorUser()->id])->assertNotFound();
    switchTo(entityUser())->getJson(committeePath($cycle))->assertForbidden();
    expect(Audit::query()->where('event', AuditEvent::CommitteeAssessorAppointed->value)->count())->toBe(1);
    switchTo($assessor)->getJson('/api/v1/auth/session')->assertOk()->assertJsonPath('data.committee_appointments.0.award_cycle_id', $cycle->id);
});

it('applies the segregation rules at appointment and initial assignment in the same cycle', function (): void {
    [$cycle, $version, $secretary] = committeeCycle();
    $initial = assessorUser();
    initiallyAssessedRequest($cycle, $version, $initial);
    $linked = assessorUser();
    linkInitialAssessor($cycle, $linked);
    $both = staffUser(RoleKey::Assessor, RoleKey::FilteringOfficer);
    addCycleMember($cycle, $both, CycleMemberRole::FilteringOfficer);

    foreach ([$initial, $linked, $both] as $conflicted) {
        switchTo($secretary)->postJson(committeePath($cycle, 'members'), ['user_id' => $conflicted->id])->assertUnprocessable()->assertJsonPath('code', 'sod_conflict');
    }

    $committee = appointCommittee($cycle, $secretary, 1)[0];
    switchTo(systemAdmin())->putJson("/api/v1/award/cycles/{$cycle->id}/initial-assessors", ['assessor_ids' => [$linked->id, $committee->id]])->assertUnprocessable()->assertJsonPath('code', 'sod_conflict');
    $secretaryAssessor = staffUser(RoleKey::AwardSecretary, RoleKey::Assessor);
    switchTo($secretary)->postJson(committeePath($cycle, 'members'), ['user_id' => $secretaryAssessor->id])->assertCreated();
    switchTo(systemAdmin())->putJson("/api/v1/award/cycles/{$cycle->id}/members", ['award_secretary_ids' => [$secretary->id, $secretaryAssessor->id]])->assertUnprocessable()->assertJsonPath('code', 'sod_conflict');
    $candidates = switchTo($secretary)->getJson(committeePath($cycle, 'candidates'))->assertOk()->json('data.*.id');
    expect($candidates)->not->toContain($initial->id)->not->toContain($committee->id)->not->toContain($linked->id);
});

it('starts the committee assessment only with an active committee assessor and on its date', function (): void {
    [$cycle, $version, $secretary, $officer] = committeeCycle();
    $assessment = initiallyAssessedRequest($cycle, $version, assessorUser());
    closeCycleSubmissions($cycle);
    qualifyRequest($cycle, $officer, $assessment);

    startCommittee($cycle, $secretary)->assertUnprocessable()->assertJsonPath('code', 'committee.none_active');
    $assessor = appointCommittee($cycle, $secretary, 1)[0];
    expect($assessment->refresh()->status->value)->toBe('qualified_for_committee');
    switchTo(requestFollower())->postJson("/api/v1/award/cycles/{$cycle->id}/start-committee")->assertForbidden();

    Date::setTestNow($cycle->refresh()->committee_start->copy()->addMinute());
    Artisan::call('moashir:award:advance-cycles');

    expect($cycle->refresh()->status->value)->toBe('committee_assessment')
        ->and($assessment->refresh()->status->value)->toBe('under_committee_assessment')
        ->and(committeeRowOf($assessor, $assessment)->status->value)->toBe('not_started');
    startCommittee($cycle, $secretary)->assertConflict()->assertJsonPath('code', 'state.transition_illegal');
});

it('distributes a request qualified after the start and seats a late appointment on every open request', function (): void {
    $scenario = committeeScenario(1, 1);
    $cycle = $scenario['cycle'];
    $late = appointCommittee($cycle, $scenario['secretary'], 1)[0];

    expect(committeeRowOf($late, $scenario['requests'][0])->status->value)->toBe('not_started')
        ->and(rosterNoticeCodes($late))->toContain(NotificationCode::CommitteeAssigned->value);
});

it('removes a committee assessor without scores and refuses once scoring started', function (): void {
    $scenario = committeeScenario(1, 2);
    [$first, $second] = $scenario['assessors'];
    $assessment = $scenario['requests'][0];
    switchTo($first)->putJson(myAssignmentPath(committeeRowOf($first, $assessment), 'scores'), ['scores' => selfScores($scenario['version'], ['M1.1' => '7'])])->assertOk();

    switchTo($scenario['secretary'])->deleteJson(committeePath($scenario['cycle'], 'members/'.seatOf($scenario['cycle'], $first)->id))->assertUnprocessable()->assertJsonPath('code', 'committee_assessor.has_scores');
    test()->deleteJson(committeePath($scenario['cycle'], 'members/'.seatOf($scenario['cycle'], $second)->id))->assertNoContent();

    expect(committeeRowOf($second, $assessment)->status->value)->toBe('voided')
        ->and(CycleCommitteeAssessor::query()->where('assessor_user_id', $second->id)->value('status'))->toBe(CommitteeMembershipStatus::Removed);
    switchTo($second)->getJson('/api/v1/committee/me/assignments')->assertOk()->assertJsonPath('meta.pagination.total', 0);
});

it('disables a committee assessor with a reason and excludes the scores with a recomputed average', function (): void {
    $scenario = committeeScenario(1, 3);
    [$first, $second, $third] = $scenario['assessors'];
    $assessment = $scenario['requests'][0];
    $cycle = $scenario['cycle'];
    submitCommitteeEvaluation($first, $assessment, $scenario['version'])->assertOk();
    submitCommitteeEvaluation($second, $assessment, $scenario['version'], COMMITTEE_TOP_MARKS)->assertOk();
    $seat = seatOf($cycle, $third);
    $admin = systemAdmin();

    switchTo($scenario['secretary'])->postJson(committeePath($cycle, "members/{$seat->id}/exclude"))->assertConflict();
    test()->postJson(committeePath($cycle, "members/{$seat->id}/disable"))->assertUnprocessable();
    test()->postJson(committeePath($cycle, "members/{$seat->id}/disable"), ['reason' => COMMITTEE_DISABLE_REASON])->assertOk()->assertJsonPath('data.status', 'disabled')->assertJsonPath('data.disabled_reason', COMMITTEE_DISABLE_REASON);
    switchTo($third)->putJson(myAssignmentPath(committeeRowOf($third, $assessment), 'scores'), ['scores' => selfScores($scenario['version'], ['M1.1' => '7'])])->assertForbidden()->assertJsonPath('code', 'committee.membership_inactive');
    expect($assessment->refresh()->status->value)->toBe('under_committee_assessment')
        ->and(rosterNoticeCodes($admin))->toContain(NotificationCode::CommitteeAssessorDisabled->value);

    switchTo($scenario['secretary'])->postJson(committeePath($cycle, "members/{$seat->id}/exclude"))->assertOk()->assertJsonPath('data.status', 'excluded')->assertJsonPath('meta.recomputed_request_ids', [$assessment->id]);

    expect($assessment->refresh()->status->value)->toBe('awaiting_secretary_approval')
        ->and(CommitteeAverage::query()->where('assessment_id', $assessment->id)->sole()->percent->toDisplay())->toBe('88.50')
        ->and(committeeRowOf($third, $assessment)->void_reason?->value)->toBe('excluded');
});

it('refuses to leave the committee without an active assessor', function (): void {
    $scenario = committeeScenario(1, 1);
    $seat = seatOf($scenario['cycle'], $scenario['assessors'][0]);

    switchTo($scenario['secretary'])->postJson(committeePath($scenario['cycle'], "members/{$seat->id}/disable"), ['reason' => COMMITTEE_DISABLE_REASON])->assertOk();
    test()->postJson(committeePath($scenario['cycle'], "members/{$seat->id}/exclude"))->assertUnprocessable()->assertJsonPath('code', 'committee.none_active');
});

it('appoints a substitute who reads the replaced scores read only until the secretary approves the result', function (): void {
    $scenario = committeeScenario(1, 2);
    [$first, $replaced] = $scenario['assessors'];
    $assessment = $scenario['requests'][0];
    $cycle = $scenario['cycle'];
    submitCommitteeEvaluation($first, $assessment, $scenario['version'])->assertOk();
    submitCommitteeEvaluation($replaced, $assessment, $scenario['version'], COMMITTEE_MID_MARKS)->assertOk();
    expect(CommitteeAverage::query()->where('assessment_id', $assessment->id)->latest('id')->firstOrFail()->percent->toDisplay())->toBe('68.50');
    $seat = seatOf($cycle, $replaced);
    $substitute = assessorUser();

    switchTo($scenario['secretary'])->postJson(committeePath($cycle, "members/{$seat->id}/disable"), ['reason' => COMMITTEE_DISABLE_REASON])->assertOk();
    test()->postJson(committeePath($cycle, "members/{$seat->id}/substitute"), ['substitute_user_id' => $replaced->id])->assertUnprocessable()->assertJsonPath('code', 'committee.candidate_invalid');
    test()->postJson(committeePath($cycle, "members/{$seat->id}/substitute"), ['substitute_user_id' => $first->id])->assertUnprocessable()->assertJsonPath('code', 'committee.member_exists');
    test()->postJson(committeePath($cycle, "members/{$seat->id}/substitute"), ['substitute_user_id' => $substitute->id])->assertCreated()->assertJsonPath('data.is_substitute', true)->assertJsonPath('data.replaces_id', $seat->id);

    $row = committeeRowOf($substitute, $assessment);
    switchTo($substitute)->getJson(myAssignmentPath($row))->assertOk()
        ->assertJsonPath('data.prior.view_only', true)
        ->assertJsonPath('data.prior.percent', '60.00')
        ->assertJsonPath('data.prior.progress.scored', 4)
        ->assertJsonPath('data.assignment.is_substitute', true);
    expect(rosterNoticeCodes($substitute))->toContain(NotificationCode::CommitteeSubstituteAssigned->value)
        ->and($assessment->refresh()->status->value)->toBe('awaiting_secretary_approval');

    switchTo($scenario['secretary'])->postJson(secretaryPath($cycle, $assessment, 'approve'))->assertConflict()->assertJsonPath('code', 'committee.substitution_pending');
    test()->postJson(secretaryPath($cycle, $assessment, "substitutions/{$row->id}/approve"))->assertConflict()->assertJsonPath('code', 'committee.substitute_not_completed');
    switchTo($first)->getJson(myAssignmentPath(committeeRowOf($first, $assessment)))->assertOk()->assertJsonPath('data.prior', null);

    submitCommitteeEvaluation($substitute, $assessment, $scenario['version'], COMMITTEE_TOP_MARKS)->assertOk();
    $admin = systemAdmin();
    switchTo($scenario['secretary'])->postJson(secretaryPath($cycle, $assessment, "substitutions/{$row->id}/approve"))->assertOk()->assertJsonPath('data.average.percent', '88.50');

    expect(CommitteeAssignment::query()->whereKey($row->replaces_assignment_id)->value('void_reason'))->toBe(CommitteeVoidReason::Replaced)
        ->and(CycleCommitteeAssessor::query()->whereKey($seat->id)->value('status'))->toBe(CommitteeMembershipStatus::Excluded)
        ->and(rosterNoticeCodes($admin))->toContain(NotificationCode::CommitteeSubstituteApproved->value)
        ->and(Audit::query()->where('event', AuditEvent::SubstituteApproved->value)->count())->toBe(1);
});

it('lets only the committee managers of the cycle change the roster', function (): void {
    $scenario = committeeScenario(1, 1);
    $seat = seatOf($scenario['cycle'], $scenario['assessors'][0]);
    $otherCycleSecretary = awardSecretary();

    switchTo($otherCycleSecretary)->postJson(committeePath($scenario['cycle'], "members/{$seat->id}/disable"), ['reason' => COMMITTEE_DISABLE_REASON])->assertNotFound();
    switchTo($scenario['assessors'][0])->postJson(committeePath($scenario['cycle'], "members/{$seat->id}/disable"), ['reason' => COMMITTEE_DISABLE_REASON])->assertForbidden();
    switchTo(superAdmin())->postJson(committeePath($scenario['cycle'], "members/{$seat->id}/disable"), ['reason' => COMMITTEE_DISABLE_REASON])->assertOk();
    expect(Assessment::query()->count())->toBeGreaterThan(0);
});
