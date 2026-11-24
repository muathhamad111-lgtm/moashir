<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Award\AwardCycleStatus;
use App\Models\Audit\Audit;
use App\Models\Award\AwardCycle;
use App\Models\Criteria\CriteriaVersion;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-01 08:00:00');
});

function publishNextCriteria(): int
{
    $draftId = switchTo(criteriaOfficer())->postJson('/api/v1/criteria/versions', [])->assertCreated()->json('data.id');
    test()->postJson("/api/v1/criteria/versions/{$draftId}/publish", ['confirm' => true, 'scope' => 'all'])->assertOk();

    return $draftId;
}

function republishedPastDraft(CriteriaVersion $current): CriteriaVersion
{
    archiveVersion($current);

    return publishedAccreditedCriteria();
}

it('binds a draft left on an older version to the published one when it opens, then freezes it', function (): void {
    $first = publishedAccreditedCriteria();
    $cycle = draftAwardCycle($first);
    linkInitialAssessor($cycle, assessorUser());
    $second = republishedPastDraft($first)->id;

    expect($cycle->refresh()->criteria_version_id)->toBe($first->id);

    switchTo(systemAdmin())->postJson("/api/v1/award/cycles/{$cycle->id}/open")->assertOk()
        ->assertJsonPath('data.status', 'submission_open')
        ->assertJsonPath('data.criteria_version_id', $second);

    $moved = Audit::query()->where('event', AuditEvent::CycleCriteriaVersionMoved->value)->where('auditable_id', $cycle->id)->sole();

    $newValues = $moved->new_values ?? [];
    ksort($newValues);

    expect($moved->old_values)->toBe(['criteria_version_id' => $first->id])
        ->and($newValues)->toBe(['criteria_version_id' => $second, 'trigger' => 'open']);

    publishNextCriteria();

    expect($cycle->refresh()->criteria_version_id)->toBe($second)
        ->and($cycle->status)->toBe(AwardCycleStatus::SubmissionOpen);
});

it('binds the draft to the published version when the schedule opens it', function (): void {
    $first = publishedAccreditedCriteria();
    $cycle = draftAwardCycle($first, [
        'submission_start' => '2026-11-01 09:00:00',
        'submission_end' => '2026-11-10 09:00:00',
        'committee_start' => '2026-11-20 09:00:00',
        'committee_end' => '2026-12-20 09:00:00',
        'announcement_at' => null,
    ]);
    linkInitialAssessor($cycle, assessorUser());
    $second = republishedPastDraft($first)->id;

    Date::setTestNow('2026-11-01 09:00:00');
    Artisan::call('moashir:award:advance-cycles');

    expect($cycle->refresh()->status)->toBe(AwardCycleStatus::SubmissionOpen)
        ->and($cycle->criteria_version_id)->toBe($second)
        ->and(Audit::query()->where('event', AuditEvent::CycleCriteriaVersionMoved->value)->where('auditable_id', $cycle->id)->exists())->toBeTrue();
});

it('opens a draft already on the published version without a version audit', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = draftAwardCycle($version);
    linkInitialAssessor($cycle, assessorUser());

    switchTo(systemAdmin())->postJson("/api/v1/award/cycles/{$cycle->id}/open")->assertOk()
        ->assertJsonPath('data.criteria_version_id', $version->id);

    expect(Audit::query()->where('event', AuditEvent::CycleCriteriaVersionMoved->value)->exists())->toBeFalse();
});

it('refuses to open a draft while no criteria version is published', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = draftAwardCycle($version);
    linkInitialAssessor($cycle, assessorUser());
    archiveVersion($version);

    switchTo(systemAdmin())->postJson("/api/v1/award/cycles/{$cycle->id}/open")
        ->assertConflict()
        ->assertJsonPath('code', 'cycle.no_published_criteria');

    expect(AwardCycle::query()->findOrFail($cycle->id)->status)->toBe(AwardCycleStatus::Draft);
});

it('checks the initial assessor before the published criteria', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = draftAwardCycle($version);
    archiveVersion($version);

    switchTo(systemAdmin())->postJson("/api/v1/award/cycles/{$cycle->id}/open")
        ->assertUnprocessable()
        ->assertJsonPath('code', 'cycle.no_initial_assessors');
});
