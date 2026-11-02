<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Award\CycleMemberRole;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Award\EntityQualified;
use App\Models\Assessments\Assessment;
use App\Models\Audit\Audit;
use App\Models\Award\AwardCycle;
use App\Models\Award\FilteringDecision;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Identity\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

const LOW_MARKS = ['M1.1' => '2', 'M1.2.1' => '3', 'M1.2.2' => '2', 'M2.1' => '3'];

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-01 08:00:00');
});

/**
 * @return array{0: AwardCycle, 1: CriteriaVersion, 2: User}
 */
function filteringCycle(): array
{
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $officer = filteringOfficer();
    addCycleMember($cycle, $officer, CycleMemberRole::FilteringOfficer);

    return [$cycle, $version, $officer];
}

function filteringPath(AwardCycle $cycle, Assessment $assessment, string $verb = ''): string
{
    return "/api/v1/award/cycles/{$cycle->id}/filtering/{$assessment->id}".($verb === '' ? '' : '/'.$verb);
}

it('lists the initial assessments of the cycle for its filtering officers and secretaries', function (): void {
    [$cycle, $version, $officer] = filteringCycle();
    $high = initiallyAssessedRequest($cycle, $version, assessorUser());
    $low = initiallyAssessedRequest($cycle, $version, assessorUser(), LOW_MARKS);
    startedInitialAssessment($cycle, $version, assessorUser());
    $secretary = awardSecretary();
    addCycleMember($cycle, $secretary, CycleMemberRole::AwardSecretary);

    switchTo($officer)->getJson("/api/v1/award/cycles/{$cycle->id}/filtering")->assertOk()
        ->assertJsonPath('meta.pagination.total', 2)
        ->assertJsonPath('meta.disqualification_score', '60.00')
        ->assertJsonPath('meta.counts.awaiting_filtering', 2)
        ->assertJsonPath('meta.below_threshold', 1)
        ->assertJsonPath('data.0.id', $high->id)
        ->assertJsonPath('data.0.initial_result.percent', '77.00')
        ->assertJsonPath('data.0.meets_threshold', true)
        ->assertJsonPath('data.1.id', $low->id)
        ->assertJsonPath('data.1.meets_threshold', false)
        ->assertJsonPath('data.0.decision', null);
    test()->getJson("/api/v1/award/cycles/{$cycle->id}/filtering?sort=initial_percent")->assertOk()->assertJsonPath('data.0.id', $low->id);
    test()->getJson("/api/v1/award/cycles/{$cycle->id}/filtering?filter[status]=draft")->assertUnprocessable();
    switchTo($secretary)->getJson("/api/v1/award/cycles/{$cycle->id}/filtering")->assertOk()->assertJsonPath('meta.pagination.total', 2);
    switchTo(systemAdmin())->getJson("/api/v1/award/cycles/{$cycle->id}/filtering")->assertOk();

    switchTo(filteringOfficer())->getJson("/api/v1/award/cycles/{$cycle->id}/filtering")->assertNotFound();
    switchTo(requestFollower())->getJson("/api/v1/award/cycles/{$cycle->id}/filtering")->assertForbidden();
    switchTo(assessorUser())->getJson("/api/v1/award/cycles/{$cycle->id}/filtering")->assertForbidden();
    switchTo(ownerOf($high->entity))->getJson("/api/v1/award/cycles/{$cycle->id}/filtering")->assertForbidden();
});

it('shows the full initial assessment read only with the report data', function (): void {
    [$cycle, $version, $officer] = filteringCycle();
    $assessor = assessorUser();
    $assessment = initiallyAssessedRequest($cycle, $version, $assessor);
    $draft = advancedDraft($cycle);

    switchTo($officer)->getJson(filteringPath($cycle, $assessment))->assertOk()
        ->assertJsonPath('data.row.status', 'awaiting_filtering')
        ->assertJsonPath('data.disqualification_score', '60.00')
        ->assertJsonPath('data.assessor.id', $assessor->id)
        ->assertJsonPath('data.review.mode', 'read_only')
        ->assertJsonPath('data.review.evidence.0.scan_status', 'clean')
        ->assertJsonPath('data.result.available', true)
        ->assertJsonPath('data.result.percent', '77.00')
        ->assertJsonPath('data.decisions', [])
        ->assertJsonStructure(['data' => ['result' => ['main', 'sub', 'feedback'], 'review' => ['scores', 'cv', 'criteria']]]);
    test()->getJson(filteringPath($cycle, $draft))->assertNotFound();

    $evidenceId = test()->getJson(filteringPath($cycle, $assessment))->json('data.review.evidence.0.id');
    test()->postJson("/api/v1/assessments/{$assessment->id}/evidence/{$evidenceId}/download")->assertOk()->assertJsonStructure(['data' => ['url', 'expires_at']]);
});

it('waits for the end of the submission period before filtering decisions', function (): void {
    [$cycle, $version, $officer] = filteringCycle();
    $assessment = initiallyAssessedRequest($cycle, $version, assessorUser());

    switchTo($officer)->postJson(filteringPath($cycle, $assessment, 'qualify'))->assertConflict()->assertJsonPath('code', 'cycle.filtering_not_open');
});

it('qualifies a request that meets the disqualification score', function (): void {
    [$cycle, $version, $officer] = filteringCycle();
    $assessment = initiallyAssessedRequest($cycle, $version, assessorUser());
    $otherOfficer = filteringOfficer();
    addCycleMember($cycle, $otherOfficer, CycleMemberRole::FilteringOfficer);
    closeCycleSubmissions($cycle);
    Event::fake([EntityQualified::class]);

    switchTo($officer)->postJson(filteringPath($cycle, $assessment, 'qualify'), ['note' => 'تأهل مستحق وفق النتيجة.'])->assertOk()
        ->assertJsonPath('data.status', 'qualified_for_committee')
        ->assertJsonPath('data.decision.outcome', 'qualified')
        ->assertJsonPath('data.decision.is_override', false)
        ->assertJsonPath('data.decision.decided_by.id', $officer->id);
    test()->postJson(filteringPath($cycle, $assessment, 'disqualify'), ['reason' => DISQUALIFY_REASON])->assertConflict();

    Event::assertDispatched(EntityQualified::class, static fn (EntityQualified $event): bool => $event->assessment->id === $assessment->id);
    expect(Audit::query()->where('event', AuditEvent::AwardQualified->value)->count())->toBe(1)
        ->and($assessment->refresh()->status->value)->toBe('qualified_for_committee');
});

it('notifies the other filtering officers of a qualification', function (): void {
    [$cycle, $version, $officer] = filteringCycle();
    $assessment = initiallyAssessedRequest($cycle, $version, assessorUser());
    $otherOfficer = filteringOfficer();
    addCycleMember($cycle, $otherOfficer, CycleMemberRole::FilteringOfficer);
    closeCycleSubmissions($cycle);

    switchTo($officer)->postJson(filteringPath($cycle, $assessment, 'qualify'))->assertOk();

    expect(financeNoticeSent($otherOfficer, NotificationCode::AdvancedQualified))->toBeTrue();
});

it('qualifies below the disqualification score only with a recorded reason and alerts the secretaries and administrators', function (): void {
    [$cycle, $version, $officer] = filteringCycle();
    $assessment = initiallyAssessedRequest($cycle, $version, assessorUser(), LOW_MARKS);
    $secretary = awardSecretary();
    addCycleMember($cycle, $secretary, CycleMemberRole::AwardSecretary);
    $admin = systemAdmin();
    closeCycleSubmissions($cycle);

    switchTo($officer)->postJson(filteringPath($cycle, $assessment, 'qualify'))->assertUnprocessable()
        ->assertJsonPath('code', 'filtering.override_reason_required')
        ->assertJsonPath('meta.threshold', '60.00')
        ->assertJsonPath('meta.score', '24.67')
        ->assertJsonValidationErrors('override_reason');
    test()->postJson(filteringPath($cycle, $assessment, 'qualify'), ['override_reason' => 'قصير'])->assertUnprocessable()->assertJsonValidationErrors('override_reason');
    test()->postJson(filteringPath($cycle, $assessment, 'qualify'), ['override_reason' => FILTERING_OVERRIDE_REASON])->assertOk()
        ->assertJsonPath('data.decision.is_override', true)
        ->assertJsonPath('data.decision.reason', FILTERING_OVERRIDE_REASON);

    expect(FilteringDecision::query()->where('assessment_id', $assessment->id)->sole()->threshold_percent->toDisplay())->toBe('60.00')
        ->and(Audit::query()->where('event', AuditEvent::AwardQualifiedOverride->value)->count())->toBe(1)
        ->and(financeNoticeSent($secretary, NotificationCode::AdvancedFilteringException))->toBeTrue()
        ->and(financeNoticeSent($admin, NotificationCode::AdvancedFilteringException))->toBeTrue();
});

it('disqualifies a request with a mandatory reason and closes it', function (): void {
    [$cycle, $version, $officer] = filteringCycle();
    $assessment = initiallyAssessedRequest($cycle, $version, assessorUser(), LOW_MARKS);
    closeCycleSubmissions($cycle);

    switchTo($officer)->postJson(filteringPath($cycle, $assessment, 'disqualify'))->assertUnprocessable()->assertJsonValidationErrors('reason');
    test()->postJson(filteringPath($cycle, $assessment, 'disqualify'), ['reason' => DISQUALIFY_REASON])->assertOk()
        ->assertJsonPath('data.status', 'not_qualified')
        ->assertJsonPath('data.decision.outcome', 'not_qualified')
        ->assertJsonPath('data.decision.reason', DISQUALIFY_REASON);

    expect($assessment->refresh()->isOpen())->toBeFalse()
        ->and(Audit::query()->where('event', AuditEvent::AwardDisqualified->value)->count())->toBe(1);
    test()->getJson("/api/v1/award/cycles/{$cycle->id}/filtering?filter[status]=not_qualified")->assertOk()->assertJsonPath('meta.pagination.total', 1);
});

it('lets only the filtering officers of the cycle decide', function (): void {
    [$cycle, $version] = filteringCycle();
    $assessment = initiallyAssessedRequest($cycle, $version, assessorUser());
    $secretary = awardSecretary();
    addCycleMember($cycle, $secretary, CycleMemberRole::AwardSecretary);
    closeCycleSubmissions($cycle);
    $path = filteringPath($cycle, $assessment, 'qualify');

    switchTo(systemAdmin())->postJson($path)->assertForbidden();
    switchTo($secretary)->postJson($path)->assertForbidden();
    switchTo(filteringOfficer())->postJson($path)->assertNotFound();
    switchTo(requestFollower())->postJson($path)->assertForbidden();

    $otherCycle = openAwardCycle($version, ['status' => 'draft']);
    switchTo(systemAdmin())->getJson("/api/v1/award/cycles/{$otherCycle->id}/filtering/{$assessment->id}")->assertNotFound();
});

it('refuses a decision by the initial assessor of the request', function (): void {
    [$cycle, $version] = filteringCycle();
    $both = staffUser(RoleKey::FilteringOfficer, RoleKey::Assessor);
    $assessment = initiallyAssessedRequest($cycle, $version, $both);
    addCycleMember($cycle, $both, CycleMemberRole::FilteringOfficer);
    closeCycleSubmissions($cycle);

    switchTo($both)->postJson(filteringPath($cycle, $assessment, 'qualify'))->assertForbidden()->assertJsonPath('code', 'sod_conflict');
});

it('excludes frozen requests from filtering', function (): void {
    [$cycle, $version, $officer] = filteringCycle();
    $assessment = initiallyAssessedRequest($cycle, $version, assessorUser());
    closeCycleSubmissions($cycle);
    softDeleteWithOpenRequests($assessment->entity, [$assessment->request_number]);

    switchTo($officer)->getJson("/api/v1/award/cycles/{$cycle->id}/filtering")->assertOk()->assertJsonPath('meta.pagination.total', 0);
    test()->postJson(filteringPath($cycle, $assessment, 'qualify'))->assertConflict()->assertJsonPath('code', 'entity.deleted');
});

it('requires a reason from the Super Admin for a filtering decision', function (): void {
    [$cycle, $version] = filteringCycle();
    $assessment = initiallyAssessedRequest($cycle, $version, assessorUser());
    closeCycleSubmissions($cycle);

    switchTo(superAdmin())->postJson(filteringPath($cycle, $assessment, 'qualify'))->assertUnprocessable()->assertJsonPath('code', 'reason_required');
    test()->postJson(filteringPath($cycle, $assessment, 'qualify'), ['reason' => SUPER_ADMIN_REASON])->assertOk();
});
