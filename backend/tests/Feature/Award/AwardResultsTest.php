<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Award\AwardCycleStatus;
use App\Enums\Notifications\NotificationCode;
use App\Events\Award\NationalResultsApproved;
use App\Models\Assessments\Assessment;
use App\Models\Audit\Audit;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use App\Notifications\Entities\EntityNotification;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-01 08:00:00');
});

/**
 * @return list<string>
 */
function resultNoticeCodes(User $user): array
{
    return array_values(array_map(
        static fn (EntityNotification $notification): string => $notification->notice->code->value,
        Notification::sent($user, EntityNotification::class)->all(),
    ));
}

/**
 * @return array{cycle: AwardCycle, secretary: User, top: Assessment, middle: Assessment, low: Assessment, rejected: Assessment, region: int}
 */
function resultsScenario(): array
{
    [$cycle, $version, $secretary, $officer] = committeeCycle();
    $requests = [];

    for ($index = 0; $index < 4; $index++) {
        $requests[] = initiallyAssessedRequest($cycle, $version, assessorUser());
    }

    closeCycleSubmissions($cycle);
    [$top, $middle, $low, $rejected] = $requests;

    foreach ([$top, $middle, $low] as $assessment) {
        qualifyRequest($cycle, $officer, $assessment);
    }

    switchTo($officer)->postJson("/api/v1/award/cycles/{$cycle->id}/filtering/{$rejected->id}/disqualify", ['reason' => DISQUALIFY_REASON])->assertOk();
    $assessors = appointCommittee($cycle, $secretary, 1);
    startCommittee($cycle, $secretary)->assertOk();
    $scenario = ['cycle' => $cycle->refresh(), 'version' => $version, 'secretary' => $secretary, 'officer' => $officer, 'assessors' => $assessors, 'requests' => [$top, $middle, $low]];

    approvedByCommittee($scenario, $top, COMMITTEE_TOP_MARKS);
    approvedByCommittee($scenario, $middle);
    approvedByCommittee($scenario, $low, COMMITTEE_MID_MARKS);

    return ['cycle' => $cycle->refresh(), 'secretary' => $secretary, 'top' => $top->refresh(), 'middle' => $middle->refresh(), 'low' => $low->refresh(), 'rejected' => $rejected->refresh(), 'region' => $cycle->regions->firstOrFail()->id];
}

function resultsPath(AwardCycle $cycle, string $suffix = ''): string
{
    return "/api/v1/award/cycles/{$cycle->id}".($suffix === '' ? '' : '/'.$suffix);
}

it('ranks the regional winners uniquely, locks the approved region and qualifies the winners for the national stage', function (): void {
    $results = resultsScenario();
    $cycle = $results['cycle'];
    $region = $results['region'];

    switchTo($results['secretary'])->getJson(resultsPath($cycle, 'results'))->assertOk()
        ->assertJsonPath('data.0.assessment_id', $results['top']->id)
        ->assertJsonPath('data.0.average_percent', '100.00')
        ->assertJsonPath('data.2.average_percent', '60.00')
        ->assertJsonPath('meta.regions.0.total', 3)
        ->assertJsonPath('meta.regions.0.approved', false);
    test()->postJson(resultsPath($cycle, "regions/{$region}/approve"))->assertUnprocessable()->assertJsonPath('code', 'winners.none');
    test()->putJson(resultsPath($cycle, "results/{$results['top']->id}/regional-rank"), ['rank' => 4])->assertUnprocessable();
    test()->putJson(resultsPath($cycle, "results/{$results['top']->id}/regional-rank"), ['rank' => 1])->assertOk()->assertJsonPath('data.regional_rank', 1);
    test()->putJson(resultsPath($cycle, "results/{$results['middle']->id}/regional-rank"), ['rank' => 1])->assertUnprocessable()->assertJsonPath('code', 'rank.taken')->assertJsonPath('meta.assessment_id', $results['top']->id);
    test()->putJson(resultsPath($cycle, "results/{$results['middle']->id}/regional-rank"), ['rank' => 2])->assertOk();
    test()->putJson(resultsPath($cycle, "results/{$results['rejected']->id}/regional-rank"), ['rank' => 3])->assertNotFound();
    switchTo(systemAdmin())->putJson(resultsPath($cycle, "results/{$results['low']->id}/regional-rank"), ['rank' => 3])->assertForbidden();
    test()->getJson(resultsPath($cycle, 'results'))->assertOk();

    $admin = systemAdmin();
    switchTo($results['secretary'])->postJson(resultsPath($cycle, "regions/{$region}/approve"))->assertOk()
        ->assertJsonPath('meta.regions.0.approved', true)
        ->assertJsonPath('meta.regions.0.ranked', 2);
    test()->postJson(resultsPath($cycle, "regions/{$region}/approve"))->assertConflict()->assertJsonPath('code', 'award.region_approved');
    test()->putJson(resultsPath($cycle, "results/{$results['low']->id}/regional-rank"), ['rank' => 3])->assertConflict()->assertJsonPath('code', 'award.region_locked');

    expect($results['top']->refresh()->status->value)->toBe('qualified_for_national')
        ->and($results['middle']->refresh()->status->value)->toBe('qualified_for_national')
        ->and($results['low']->refresh()->status->value)->toBe('non_winner')
        ->and(resultNoticeCodes($admin))->toContain(NotificationCode::RegionApproved->value, NotificationCode::QualifiedForNational->value)
        ->and(Audit::query()->where('event', AuditEvent::AwardRegionApproved->value)->count())->toBe(1);
    switchTo(ownerOf($results['low']->entity))->getJson("/api/v1/assessments/{$results['low']->id}")->assertOk()->assertJsonPath('data.status', 'under_review');
});

it('refuses a region approval while a request of the region has no approved result', function (): void {
    $scenario = committeeScenario(1, 1);
    $cycle = $scenario['cycle'];
    $region = $cycle->regions->firstOrFail()->id;

    switchTo($scenario['secretary'])->postJson(resultsPath($cycle, "regions/{$region}/approve"))->assertConflict()
        ->assertJsonPath('code', 'region.requests_pending')
        ->assertJsonPath('meta.pending.0.id', $scenario['requests'][0]->id);
    test()->postJson(resultsPath($cycle, 'national/approve'))->assertConflict()->assertJsonPath('code', 'regions.pending');
});

it('approves the national result and lets only the award secretary announce it manually', function (): void {
    $results = resultsScenario();
    $cycle = $results['cycle'];
    $region = $results['region'];
    switchTo($results['secretary'])->putJson(resultsPath($cycle, "results/{$results['top']->id}/regional-rank"), ['rank' => 1])->assertOk();
    test()->putJson(resultsPath($cycle, "results/{$results['middle']->id}/regional-rank"), ['rank' => 2])->assertOk();
    test()->postJson(resultsPath($cycle, "regions/{$region}/approve"))->assertOk();

    test()->postJson(resultsPath($cycle, 'announce'))->assertConflict();
    test()->postJson(resultsPath($cycle, 'national/approve'))->assertUnprocessable()->assertJsonPath('code', 'winners.none');
    test()->putJson(resultsPath($cycle, "national/{$results['low']->id}/rank"), ['rank' => 1])->assertConflict();
    test()->putJson(resultsPath($cycle, "national/{$results['middle']->id}/rank"), ['rank' => 1])->assertOk()->assertJsonPath('data.national_rank', 1);
    test()->getJson(resultsPath($cycle, 'national'))->assertOk()->assertJsonPath('data.0.assessment_id', $results['middle']->id)->assertJsonPath('meta.national.approved', false);
    Event::fake([NationalResultsApproved::class]);
    test()->postJson(resultsPath($cycle, 'national/approve'))->assertOk()->assertJsonPath('meta.national.approved', true)->assertJsonPath('meta.cycle_status', 'approved');
    Event::assertDispatched(NationalResultsApproved::class);
    test()->putJson(resultsPath($cycle, "national/{$results['top']->id}/rank"), ['rank' => 2])->assertConflict()->assertJsonPath('code', 'award.national_approved');

    expect($results['middle']->refresh()->status->value)->toBe('national_winner')
        ->and($results['top']->refresh()->status->value)->toBe('national_non_winner')
        ->and($cycle->refresh()->status)->toBe(AwardCycleStatus::Approved);

    $owner = ownerOf($results['middle']->entity);
    switchTo($owner)->getJson("/api/v1/assessments/{$results['middle']->id}/result")->assertOk()->assertJsonPath('data.available', false)->assertJsonPath('data.status', 'under_review');
    switchTo(systemAdmin())->postJson(resultsPath($cycle, 'announce'))->assertForbidden();
    switchTo(awardSecretary())->postJson(resultsPath($cycle, 'announce'))->assertNotFound();
    $admin = systemAdmin();
    switchTo($results['secretary'])->postJson(resultsPath($cycle, 'announce'))->assertOk()->assertJsonPath('data.status', 'announced');

    expect(resultNoticeCodes($owner))->toContain(
        NotificationCode::AdvancedQualified->value,
        NotificationCode::AwardResultsAnnounced->value,
        NotificationCode::RegionalResult->value,
        NotificationCode::NationalResult->value,
    )->and(resultNoticeCodes(ownerOf($results['rejected']->entity)))->toContain(NotificationCode::AdvancedNotQualified->value, NotificationCode::AwardResultsAnnounced->value)
        ->and(resultNoticeCodes(ownerOf($results['rejected']->entity)))->not->toContain(NotificationCode::RegionalResult->value)
        ->and(resultNoticeCodes(ownerOf($results['low']->entity)))->not->toContain(NotificationCode::NationalResult->value)
        ->and(resultNoticeCodes($admin))->toContain(NotificationCode::AwardResultsAnnounced->value);

    switchTo($owner)->getJson("/api/v1/assessments/{$results['middle']->id}")->assertOk()->assertJsonPath('data.status', 'national_winner');
    switchTo($owner)->getJson("/api/v1/assessments/{$results['middle']->id}/result")->assertOk()
        ->assertJsonPath('data.available', true)
        ->assertJsonPath('data.percent', '77.00')
        ->assertJsonPath('data.award.status', 'national_winner')
        ->assertJsonPath('data.award.regional_rank', 2)
        ->assertJsonPath('data.award.national_rank', 1)
        ->assertJsonStructure(['data' => ['feedback', 'main', 'sub']]);
    expect(json_encode(test()->getJson("/api/v1/assessments/{$results['middle']->id}/result")->json(), JSON_THROW_ON_ERROR))->not->toContain('100.00');
    switchTo(ownerOf($results['rejected']->entity))->getJson("/api/v1/assessments/{$results['rejected']->id}/result")->assertOk()->assertJsonPath('data.available', true)->assertJsonPath('data.award.status', 'not_qualified');

    switchTo($results['secretary'])->postJson(resultsPath($cycle, 'close'))->assertOk()->assertJsonPath('data.status', 'closed');
});

it('lets only the Super Admin unapprove a region with a reason before the national approval', function (): void {
    $results = resultsScenario();
    $cycle = $results['cycle'];
    $region = $results['region'];
    switchTo($results['secretary'])->putJson(resultsPath($cycle, "results/{$results['top']->id}/regional-rank"), ['rank' => 1])->assertOk();
    test()->postJson(resultsPath($cycle, "regions/{$region}/approve"))->assertOk();

    test()->postJson(resultsPath($cycle, "regions/{$region}/unapprove"), ['reason' => UNAPPROVE_REASON])->assertForbidden();
    switchTo(superAdmin())->postJson(resultsPath($cycle, "regions/{$region}/unapprove"), ['reason' => 'قصير'])->assertUnprocessable();
    test()->postJson(resultsPath($cycle, "regions/{$region}/unapprove"), ['reason' => UNAPPROVE_REASON])->assertOk()->assertJsonPath('meta.regions.0.approved', false);

    expect($results['top']->refresh()->status->value)->toBe('approved')
        ->and($results['low']->refresh()->status->value)->toBe('approved')
        ->and(Audit::query()->where('event', AuditEvent::AwardRegionUnapproved->value)->value('reason'))->toBe(UNAPPROVE_REASON);
    switchTo($results['secretary'])->putJson(resultsPath($cycle, "results/{$results['low']->id}/regional-rank"), ['rank' => 2])->assertOk();
});
