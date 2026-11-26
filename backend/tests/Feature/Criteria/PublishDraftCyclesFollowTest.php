<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Criteria\CriteriaVersionStatus;
use App\Models\Audit\Audit;
use App\Models\Award\AwardCycle;
use App\Models\Criteria\CriteriaVersion;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-01 08:00:00');
});

it('moves every draft award cycle to the version being published and leaves an opened cycle on its own', function (): void {
    $version = publishedAccreditedCriteria();
    $first = draftAwardCycle($version);
    $second = draftAwardCycle($version);
    $opened = openAwardCycle($version);
    $nextId = switchTo(criteriaOfficer())->postJson('/api/v1/criteria/versions', [])->assertCreated()->json('data.id');

    test()->postJson("/api/v1/criteria/versions/{$nextId}/publish", ['confirm' => true, 'scope' => 'all'])->assertOk()
        ->assertJsonPath('meta.award_cycles.moved_cycle_ids', [$first->id, $second->id])
        ->assertJsonMissingPath('meta.award_cycles.draft_cycles');

    expect(AwardCycle::query()->whereKey([$first->id, $second->id])->pluck('criteria_version_id')->unique()->values()->all())->toBe([$nextId])
        ->and(AwardCycle::query()->findOrFail($opened->id)->criteria_version_id)->toBe($version->id)
        ->and(Audit::query()->where('event', AuditEvent::CycleCriteriaVersionMoved->value)->count())->toBe(2);
});

it('refuses the retired draft cycle choice with its own code and publishes nothing', function (string $choice): void {
    $version = publishedAccreditedCriteria();
    $draft = draftAwardCycle($version);
    $nextId = switchTo(criteriaOfficer())->postJson('/api/v1/criteria/versions', [])->assertCreated()->json('data.id');

    test()->postJson("/api/v1/criteria/versions/{$nextId}/publish", ['confirm' => true, 'scope' => 'all', 'draft_cycles' => $choice])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'criteria.draft_cycles_follow_published')
        ->assertJsonValidationErrors('draft_cycles');

    expect(CriteriaVersion::query()->findOrFail($nextId)->status)->toBe(CriteriaVersionStatus::Draft)
        ->and($draft->refresh()->criteria_version_id)->toBe($version->id);
})->with(['keep', 'move']);

it('still refuses publishing to a user without the permission before looking at the retired choice', function (): void {
    publishedAccreditedCriteria();
    $nextId = switchTo(criteriaOfficer())->postJson('/api/v1/criteria/versions', [])->assertCreated()->json('data.id');

    switchTo(requestFollower())->postJson("/api/v1/criteria/versions/{$nextId}/publish", ['confirm' => true, 'scope' => 'all', 'draft_cycles' => 'keep'])
        ->assertForbidden();
});
