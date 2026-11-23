<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Award\AwardCycleStatus;
use App\Enums\Award\CycleMemberRole;
use App\Models\Assessments\Assessment;
use App\Models\Audit\Audit;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-01 08:00:00');
});

/**
 * @return array{cycle: AwardCycle, officer: User, admin: User, first: Assessment, second: Assessment}
 */
function filteringWindowCycle(): array
{
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $officer = filteringOfficer();
    addCycleMember($cycle, $officer, CycleMemberRole::FilteringOfficer);

    return [
        'cycle' => $cycle,
        'officer' => $officer,
        'admin' => systemAdmin(),
        'first' => initiallyAssessedRequest($cycle, $version, assessorUser()),
        'second' => initiallyAssessedRequest($cycle, $version, assessorUser()),
    ];
}

function filteringWindowPath(AwardCycle $cycle, Assessment $assessment, string $verb): string
{
    return "/api/v1/award/cycles/{$cycle->id}/filtering/{$assessment->id}/{$verb}";
}

function moveCycleTo(AwardCycle $cycle, AwardCycleStatus $status): void
{
    $now = Date::now();
    $approved = in_array($status, [AwardCycleStatus::Approved, AwardCycleStatus::Announced], true);

    $cycle->forceFill([
        'status' => $status,
        'national_approved_at' => $approved ? $now : null,
        'announced_at' => $status === AwardCycleStatus::Announced ? $now : null,
    ])->save();
}

it('lets the filtering officer decide while submissions are still open', function (): void {
    ['cycle' => $cycle, 'officer' => $officer, 'first' => $first, 'second' => $second] = filteringWindowCycle();

    switchTo($officer)->postJson(filteringWindowPath($cycle, $first, 'qualify'))->assertOk()->assertJsonPath('data.status', 'qualified_for_committee');
    test()->postJson(filteringWindowPath($cycle, $second, 'disqualify'), ['reason' => DISQUALIFY_REASON])->assertOk()->assertJsonPath('data.status', 'not_qualified');

    expect($cycle->refresh()->status)->toBe(AwardCycleStatus::SubmissionOpen)
        ->and(Audit::query()->where('event', AuditEvent::AwardQualified->value)->count())->toBe(1)
        ->and(Audit::query()->where('event', AuditEvent::AwardDisqualified->value)->count())->toBe(1);
});

it('opens filtering decisions from submission_open to awaiting_secretary and refuses them before and after', function (AwardCycleStatus $status, bool $allowed): void {
    ['cycle' => $cycle, 'officer' => $officer, 'first' => $first] = filteringWindowCycle();
    moveCycleTo($cycle, $status);

    $response = switchTo($officer)->postJson(filteringWindowPath($cycle, $first, 'qualify'));

    $allowed
        ? $response->assertOk()->assertJsonPath('data.status', 'qualified_for_committee')
        : $response->assertConflict()->assertJsonPath('code', 'cycle.filtering_not_open')->assertJsonStructure(['message', 'code']);
})->with([
    'draft, before the window' => [AwardCycleStatus::Draft, false],
    'submissions open, first edge' => [AwardCycleStatus::SubmissionOpen, true],
    'submissions closed' => [AwardCycleStatus::SubmissionClosed, true],
    'committee assessment' => [AwardCycleStatus::CommitteeAssessment, true],
    'awaiting the secretary, last edge' => [AwardCycleStatus::AwaitingSecretary, true],
    'results approved, after the window' => [AwardCycleStatus::Approved, false],
    'announced' => [AwardCycleStatus::Announced, false],
]);

it('refuses a filtering decision to staff who are not filtering officers of the cycle', function (): void {
    ['cycle' => $cycle, 'first' => $first] = filteringWindowCycle();
    $path = filteringWindowPath($cycle, $first, 'qualify');

    $secretary = awardSecretary();
    addCycleMember($cycle, $secretary, CycleMemberRole::AwardSecretary);

    switchTo(systemAdmin())->postJson($path)->assertForbidden();
    switchTo(requestFollower())->postJson($path)->assertForbidden();
    switchTo($secretary)->postJson($path)->assertForbidden();
    switchTo(filteringOfficer())->postJson($path)->assertNotFound();
});

it('locks the qualification threshold once the cycle has a filtering decision', function (): void {
    ['cycle' => $cycle, 'officer' => $officer, 'admin' => $admin, 'first' => $first] = filteringWindowCycle();
    $cyclePath = "/api/v1/award/cycles/{$cycle->id}";

    switchTo($admin)->getJson($cyclePath)->assertOk()->assertJsonPath('data.threshold_locked', false);
    test()->putJson($cyclePath, ['disqualification_score' => '55.00'])->assertOk()->assertJsonPath('data.conditions.disqualification_score', '55.00');

    switchTo($officer)->postJson(filteringWindowPath($cycle, $first, 'qualify'))->assertOk();

    switchTo($admin)->putJson($cyclePath, ['disqualification_score' => '70.00'])->assertConflict()
        ->assertJsonPath('code', 'cycle.threshold_locked')
        ->assertJsonValidationErrors('disqualification_score');
    test()->putJson($cyclePath, ['disqualification_score' => '55.00', 'min_entity_age_years' => 4])->assertOk()
        ->assertJsonPath('data.threshold_locked', true)
        ->assertJsonPath('data.conditions.min_entity_age_years', 4);

    expect($cycle->refresh()->disqualification_score->toDisplay())->toBe('55.00')
        ->and(Audit::query()->where('event', AuditEvent::CycleUpdated->value)->count())->toBe(2)
        ->and(Audit::query()->where('event', AuditEvent::AwardQualified->value)->count())->toBe(1);
});
