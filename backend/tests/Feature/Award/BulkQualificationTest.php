<?php

declare(strict_types=1);

use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\AwardCycleStatus;
use App\Enums\Award\CycleMemberRole;
use App\Enums\Identity\RoleKey;
use App\Models\Assessments\Assessment;
use App\Models\Audit\Audit;
use App\Models\Award\AwardCycle;
use App\Models\Award\FilteringDecision;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Identity\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

// «تأهيل كل من بلغ حد التأهل» (operator decision D-4, 2026-11-22): one confirmed action qualifies every request
// awaiting filtering that reaches the threshold; below-threshold requests stay for an individual decision.

const BULK_LOW_MARKS = ['M1.1' => '2', 'M1.2.1' => '3', 'M1.2.2' => '2', 'M2.1' => '3'];

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-01 08:00:00');
});

/**
 * @return array{0: AwardCycle, 1: CriteriaVersion, 2: User}
 */
function bulkCycle(): array
{
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $officer = filteringOfficer();
    addCycleMember($cycle, $officer, CycleMemberRole::FilteringOfficer);

    return [$cycle, $version, $officer];
}

function bulkPath(AwardCycle $cycle): string
{
    return "/api/v1/award/cycles/{$cycle->id}/filtering/qualify-eligible";
}

function bulkStatusOf(Assessment $assessment): mixed
{
    return $assessment->refresh()->status;
}

it('previews and qualifies every request that reaches the threshold, leaving the ones below it', function (): void {
    [$cycle, $version, $officer] = bulkCycle();
    $first = initiallyAssessedRequest($cycle, $version, assessorUser());
    $second = initiallyAssessedRequest($cycle, $version, assessorUser());
    $low = initiallyAssessedRequest($cycle, $version, assessorUser(), BULK_LOW_MARKS);

    switchTo($officer)->getJson(bulkPath($cycle))->assertOk()
        ->assertJsonPath('data.count', 2)
        ->assertJsonPath('data.threshold_percent', '60.00')
        ->assertJsonPath('data.own_assessments', 0)
        ->assertJsonPath('data.requests.0.id', $first->id)
        ->assertJsonPath('data.requests.1.id', $second->id);

    switchTo($officer)->postJson(bulkPath($cycle))->assertOk()
        ->assertJsonPath('data.qualified', 2)
        ->assertJsonPath('data.qualified_ids', [$first->id, $second->id])
        ->assertJsonPath('data.skipped', []);

    expect(bulkStatusOf($first))->toBe(AdvancedAssessmentStatus::QualifiedForCommittee)
        ->and(bulkStatusOf($second))->toBe(AdvancedAssessmentStatus::QualifiedForCommittee)
        ->and(bulkStatusOf($low))->toBe(AdvancedAssessmentStatus::AwaitingFiltering)
        ->and(FilteringDecision::query()->whereIn('assessment_id', [$first->id, $second->id])->count())->toBe(2)
        ->and(Audit::query()->where('event', AuditEvent::AwardQualified->value)->count())->toBe(2);
});

it('qualifies nothing new when run twice', function (): void {
    [$cycle, $version, $officer] = bulkCycle();
    initiallyAssessedRequest($cycle, $version, assessorUser());

    switchTo($officer)->postJson(bulkPath($cycle))->assertOk()->assertJsonPath('data.qualified', 1);
    switchTo($officer)->postJson(bulkPath($cycle))->assertOk()->assertJsonPath('data.qualified', 0);

    expect(FilteringDecision::query()->count())->toBe(1);
});

it('leaves out a request the officer assessed, which they may not decide', function (): void {
    [$cycle, $version] = bulkCycle();
    $officer = staffUser(RoleKey::FilteringOfficer, RoleKey::Assessor);
    $mine = initiallyAssessedRequest($cycle, $version, $officer);
    $other = initiallyAssessedRequest($cycle, $version, assessorUser());
    addCycleMember($cycle, $officer, CycleMemberRole::FilteringOfficer);

    switchTo($officer)->getJson(bulkPath($cycle))->assertOk()
        ->assertJsonPath('data.count', 1)
        ->assertJsonPath('data.own_assessments', 1);
    switchTo($officer)->postJson(bulkPath($cycle))->assertOk()->assertJsonPath('data.qualified_ids', [$other->id]);

    expect(bulkStatusOf($mine))->toBe(AdvancedAssessmentStatus::AwaitingFiltering);
});

it('refuses outside the filtering window', function (): void {
    [$cycle, $version, $officer] = bulkCycle();
    initiallyAssessedRequest($cycle, $version, assessorUser());
    $cycle->forceFill(['status' => AwardCycleStatus::Approved, 'national_approved_at' => Date::now()])->save();

    switchTo($officer)->postJson(bulkPath($cycle))->assertConflict()->assertJsonPath('code', 'cycle.filtering_not_open');
});

it('lets only the cycle filtering officers decide in bulk', function (): void {
    [$cycle, $version] = bulkCycle();
    initiallyAssessedRequest($cycle, $version, assessorUser());
    $secretary = awardSecretary();
    addCycleMember($cycle, $secretary, CycleMemberRole::AwardSecretary);

    switchTo($secretary)->postJson(bulkPath($cycle))->assertForbidden();
    switchTo(filteringOfficer())->postJson(bulkPath($cycle))->assertNotFound();
    switchTo(filteringOfficer())->getJson(bulkPath($cycle))->assertNotFound();

    expect(FilteringDecision::query()->count())->toBe(0);
});

it('asks the Super Admin for one reason before deciding in bulk', function (): void {
    [$cycle, $version] = bulkCycle();
    initiallyAssessedRequest($cycle, $version, assessorUser());

    switchTo(superAdmin())->postJson(bulkPath($cycle))->assertUnprocessable()->assertJsonPath('code', 'reason_required');
    switchTo(superAdmin())->postJson(bulkPath($cycle), ['reason' => 'تأهيل جماعي بعد مراجعة القائمة كاملة'])->assertOk()
        ->assertJsonPath('data.qualified', 1);
});

it('lets one employee hold the follower and filtering officer roles with the full permissions of both', function (): void {
    // Operator, 2026-11-22: «نعم يسمح وفي حال المنح يكون له كل صلاحيات الدورين بدون قيود».
    [$cycle, $version] = bulkCycle();
    $both = staffUser(RoleKey::RequestFollower, RoleKey::FilteringOfficer);
    addCycleMember($cycle, $both, CycleMemberRole::FilteringOfficer);
    $assessment = initiallyAssessedRequest($cycle, $version, assessorUser());

    switchTo($both)->getJson('/api/v1/requests?filter[path]=advanced')->assertOk();
    switchTo($both)->postJson(bulkPath($cycle))->assertOk()->assertJsonPath('data.qualified_ids', [$assessment->id]);
});
