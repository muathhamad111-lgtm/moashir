<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Award\CycleMemberRole;
use App\Models\Audit\Audit;
use App\Models\Award\AwardCycle;
use App\Models\Entities\Region;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-01 08:00:00');
});

const CYCLE_DESCRIPTION = [
    'ar' => 'دورة تكرّم الجهات التي تميزت في التحول المؤسسي.',
    'en' => 'A cycle that honours the entities that excelled in institutional transformation.',
];

it('creates a draft cycle with its description and the published criteria version', function (): void {
    $version = publishedAccreditedCriteria();

    $id = switchTo(systemAdmin())->postJson('/api/v1/award/cycles', cyclePayload(Region::factory()->create(), ['description' => CYCLE_DESCRIPTION]))
        ->assertCreated()
        ->assertJsonPath('data.description', CYCLE_DESCRIPTION)
        ->assertJsonPath('data.criteria_version_id', $version->id)
        ->assertJsonPath('data.locked_region_ids', [])
        ->json('data.id');

    expect(AwardCycle::query()->findOrFail($id)->description?->toArray())->toBe(CYCLE_DESCRIPTION);
});

it('creates a cycle without a description and covers every active region when none is chosen', function (): void {
    publishedAccreditedCriteria();
    $regions = Region::factory()->count(2)->create();
    Region::factory()->inactive()->create();
    $payload = cyclePayload($regions->firstOrFail());
    unset($payload['region_ids']);

    switchTo(systemAdmin())->postJson('/api/v1/award/cycles', $payload)->assertCreated()
        ->assertJsonPath('data.description', null)
        ->assertJsonCount(2, 'data.regions');
});

it('refuses a criteria version chosen by the client on create and on update', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = draftAwardCycle($version);
    $admin = systemAdmin();

    switchTo($admin)->postJson('/api/v1/award/cycles', cyclePayload(Region::factory()->create(), ['criteria_version_id' => $version->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('criteria_version_id');
    test()->putJson("/api/v1/award/cycles/{$cycle->id}", ['criteria_version_id' => $version->id])
        ->assertUnprocessable()->assertJsonValidationErrors('criteria_version_id');

    expect(AwardCycle::query()->count())->toBe(1);
});

it('refuses an invalid cycle description', function (mixed $description, string $field): void {
    $cycle = draftAwardCycle(publishedAccreditedCriteria());

    switchTo(systemAdmin())->putJson("/api/v1/award/cycles/{$cycle->id}", ['description' => $description])
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'Arabic only' => [['ar' => 'تعريف بالعربية فقط'], 'description.en'],
    'English only' => [['en' => 'English only'], 'description.ar'],
    'blank Arabic' => [['ar' => '   ', 'en' => 'English text'], 'description.ar'],
    'too long' => [['ar' => str_repeat('ت', 2001), 'en' => 'English text'], 'description.ar'],
    'an unknown language' => [['ar' => 'نص', 'en' => 'Text', 'fr' => 'Texte'], 'description'],
    'not an object' => ['plain text', 'description'],
]);

it('accepts a description at the length limit, updates it and clears it', function (): void {
    $cycle = draftAwardCycle(publishedAccreditedCriteria());
    $longest = ['ar' => str_repeat('ت', 2000), 'en' => str_repeat('e', 2000)];

    switchTo(systemAdmin())->putJson("/api/v1/award/cycles/{$cycle->id}", ['description' => $longest])->assertOk()
        ->assertJsonPath('data.description', $longest);
    test()->putJson("/api/v1/award/cycles/{$cycle->id}", ['description' => CYCLE_DESCRIPTION])->assertOk()
        ->assertJsonPath('data.description', CYCLE_DESCRIPTION);
    test()->putJson("/api/v1/award/cycles/{$cycle->id}", ['description' => null])->assertOk()
        ->assertJsonPath('data.description', null);

    $audits = Audit::query()->where('event', AuditEvent::CycleUpdated->value)->where('auditable_id', $cycle->id)->orderBy('id')->get();

    expect($audits)->toHaveCount(3)
        ->and($audits->last()?->old_values)->toBe(['description' => CYCLE_DESCRIPTION])
        ->and($audits->last()?->new_values)->toBe(['description' => null]);
});

it('saves each wizard step on its own and touches only the fields of that step', function (string $step, array $payload): void {
    $cycle = draftAwardCycle(publishedAccreditedCriteria());

    switchTo(systemAdmin())->putJson("/api/v1/award/cycles/{$cycle->id}", $payload)->assertOk();

    $audit = Audit::query()->where('event', AuditEvent::CycleUpdated->value)->where('auditable_id', $cycle->id)->sole();

    expect(array_keys($audit->new_values ?? []))->toEqualCanonicalizing(array_keys($payload), $step);
})->with([
    'identity' => ['identity', ['name' => ['ar' => 'دورة 2027', 'en' => 'Cycle 2027'], 'year' => 2027, 'description' => CYCLE_DESCRIPTION]],
    'dates' => ['dates', [
        'submission_start' => '2026-11-10T06:00:00+00:00',
        'submission_end' => '2026-12-10T20:59:00+00:00',
        'committee_start' => '2026-12-20T06:00:00+00:00',
        'committee_end' => '2027-01-20T20:59:00+00:00',
        'announcement_at' => '2027-02-01T06:00:00+00:00',
    ]],
    'conditions' => ['conditions', ['min_entity_age_years' => 5, 'disqualification_score' => '65.5', 'participation_certificate_enabled' => true]],
    'deadlines' => ['deadlines', ['initial_review_sla_days' => 21]],
    'fee' => ['fee', ['entry_fee' => '250.00']],
]);

it('saves the regions of the conditions step and returns them in the region order', function (): void {
    $cycle = draftAwardCycle(publishedAccreditedCriteria());
    $regions = Region::factory()->count(3)->create();
    $ordered = array_values(Region::query()->whereKey($regions->modelKeys())->orderBy('sort_order')->orderBy('id')->pluck('id')->all());

    $response = switchTo(systemAdmin())->putJson("/api/v1/award/cycles/{$cycle->id}", ['region_ids' => array_reverse($regions->modelKeys())])->assertOk()
        ->assertJsonCount(3, 'data.regions');

    expect(array_column($response->json('data.regions'), 'id'))->toBe($ordered)
        ->and(Audit::query()->where('event', AuditEvent::CycleUpdated->value)->where('auditable_id', $cycle->id)->sole()->new_values)
        ->toBe(['region_ids' => $ordered]);
});

it('keeps a draft cycle on the published criteria version and freezes the version once submissions open', function (): void {
    $first = publishedAccreditedCriteria();
    $draft = draftAwardCycle($first);
    $open = openAwardCycle($first);
    archiveVersion($first);
    $second = publishedAccreditedCriteria();
    $admin = systemAdmin();

    switchTo($admin)->putJson("/api/v1/award/cycles/{$draft->id}", ['year' => 2027])->assertOk()
        ->assertJsonPath('data.criteria_version_id', $second->id)
        ->assertJsonPath('data.scale_kind', null);
    test()->putJson("/api/v1/award/cycles/{$open->id}", ['initial_review_sla_days' => 20])->assertOk()
        ->assertJsonPath('data.criteria_version_id', $first->id);

    $audit = Audit::query()->where('event', AuditEvent::CycleUpdated->value)->where('auditable_id', $draft->id)->sole();

    expect($audit->old_values)->toBe(['year' => $draft->year, 'criteria_version_id' => $first->id])
        ->and($audit->new_values)->toBe(['year' => 2027, 'criteria_version_id' => $second->id])
        ->and(AwardCycle::query()->findOrFail($open->id)->criteria_version_id)->toBe($first->id);
});

it('lists the regions that already have requests as locked', function (): void {
    $cycle = openAwardCycle(publishedAccreditedCriteria());
    advancedDraft($cycle);

    switchTo(systemAdmin())->getJson("/api/v1/award/cycles/{$cycle->id}")->assertOk()
        ->assertJsonPath('data.locked_region_ids', [$cycle->regions->firstOrFail()->id]);
});

it('lets only the cycle secretary and the System Administrator edit the cycle setup', function (): void {
    $cycle = draftAwardCycle(publishedAccreditedCriteria());
    $member = awardSecretary();
    addCycleMember($cycle, $member, CycleMemberRole::AwardSecretary);
    $owner = ownerOf(entityWithOwner());

    switchTo(awardSecretary())->putJson("/api/v1/award/cycles/{$cycle->id}", ['description' => CYCLE_DESCRIPTION])->assertForbidden();
    switchTo($owner)->putJson("/api/v1/award/cycles/{$cycle->id}", ['description' => CYCLE_DESCRIPTION])->assertNotFound();
    test()->postJson('/api/v1/award/cycles', cyclePayload(Region::factory()->create(), ['description' => CYCLE_DESCRIPTION]))->assertForbidden();
    switchTo($member)->putJson("/api/v1/award/cycles/{$cycle->id}", ['description' => CYCLE_DESCRIPTION])->assertOk()
        ->assertJsonPath('data.description', CYCLE_DESCRIPTION);

    expect(AwardCycle::query()->count())->toBe(1);
});

it('shows the cycle description on the public award intro and on the entity award path', function (): void {
    $cycle = openAwardCycle(publishedAccreditedCriteria(), ['description' => CYCLE_DESCRIPTION]);
    $owner = ownerOf(cycleEntity($cycle));

    test()->getJson('/api/v1/award/cycles/current')->assertOk()->assertJsonPath('data.description', CYCLE_DESCRIPTION);
    test()->getJson('/api/v1/award/cycles/open')->assertOk()->assertJsonPath('data.0.description', CYCLE_DESCRIPTION);

    expect(boardWidget(boardOf($owner, 'entity'), 'paths')['award']['cycle']['description'])->toBe(CYCLE_DESCRIPTION);
});
