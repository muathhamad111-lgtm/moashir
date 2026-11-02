<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Award\AwardCycleStatus;
use App\Enums\Award\CycleMemberRole;
use App\Enums\Identity\RoleKey;
use App\Models\Audit\Audit;
use App\Models\Award\AwardCycle;
use App\Models\Award\AwardCycleAssessor;
use App\Models\Award\AwardCycleMember;
use App\Models\Entities\Region;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-01 08:00:00');
});

it('creates a draft cycle with the settings defaults and makes the creating secretary a member', function (): void {
    $version = publishedAccreditedCriteria();
    $region = Region::factory()->create();
    $secretary = awardSecretary();

    $response = switchTo($secretary)->postJson('/api/v1/award/cycles', cyclePayload($version, $region))->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.year', 2026)
        ->assertJsonPath('data.scale_kind', 'of_10')
        ->assertJsonPath('data.conditions.min_entity_age_years', 3)
        ->assertJsonPath('data.conditions.disqualification_score', '60.00')
        ->assertJsonPath('data.initial_review_sla_days', 14)
        ->assertJsonPath('data.participation_certificate_enabled', false)
        ->assertJsonPath('data.regions.0.id', $region->id)
        ->assertJsonPath('data.can_manage', true)
        ->assertJsonPath('data.members.0.id', $secretary->id)
        ->assertJsonPath('data.members.0.role', 'award_secretary');

    $cycle = AwardCycle::query()->findOrFail($response->json('data.id'));

    expect(Audit::query()->where('event', AuditEvent::CycleCreated->value)->where('auditable_id', $cycle->id)->exists())->toBeTrue();

    switchTo(systemAdmin())->postJson('/api/v1/award/cycles', cyclePayload($version, $region, [
        'scale_kind' => 'relative',
        'min_entity_age_years' => 5,
        'disqualification_score' => '72.5',
        'initial_review_sla_days' => 10,
        'participation_certificate_enabled' => true,
    ]))->assertCreated()
        ->assertJsonPath('data.scale_kind', 'relative')
        ->assertJsonPath('data.conditions.min_entity_age_years', 5)
        ->assertJsonPath('data.conditions.disqualification_score', '72.50')
        ->assertJsonPath('data.participation_certificate_enabled', true)
        ->assertJsonPath('data.members', []);
});

it('refuses invalid cycle definitions', function (array $overrides, string $field): void {
    $version = publishedAccreditedCriteria();

    switchTo(systemAdmin())->postJson('/api/v1/award/cycles', cyclePayload($version, Region::factory()->create(), $overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'submission ends before it starts' => [['submission_end' => '2026-11-04T06:00:00+00:00'], 'submission_end'],
    'committee before submissions end' => [['committee_start' => '2026-12-01T06:00:00+00:00'], 'committee_start'],
    'no region' => [['region_ids' => []], 'region_ids'],
    'unknown region' => [['region_ids' => [999999]], 'region_ids.0'],
    'score out of range' => [['disqualification_score' => '120'], 'disqualification_score'],
    'score with three decimals' => [['disqualification_score' => '60.125'], 'disqualification_score'],
    'missing Arabic name' => [['name' => ['en' => 'Award 2026']], 'name.ar'],
]);

it('binds a cycle only to the published criteria version', function (): void {
    publishedAccreditedCriteria();
    $draft = draftVersion();

    switchTo(systemAdmin())->postJson('/api/v1/award/cycles', cyclePayload($draft, Region::factory()->create()))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'cycle.criteria_version_not_published');
});

it('lets only the cycle managers create cycles', function (RoleKey $role): void {
    $version = publishedAccreditedCriteria();

    switchTo(staffUser($role))->postJson('/api/v1/award/cycles', cyclePayload($version, Region::factory()->create()))->assertForbidden();
})->with([RoleKey::RequestFollower, RoleKey::FilteringOfficer, RoleKey::Assessor, RoleKey::CriteriaOfficer, RoleKey::FinanceOfficer]);

it('refuses cycle management to entity accounts', function (): void {
    $version = publishedAccreditedCriteria();
    $owner = ownerOf(entityWithOwner());

    switchTo($owner)->postJson('/api/v1/award/cycles', cyclePayload($version, Region::factory()->create()))->assertForbidden();
    test()->getJson('/api/v1/award/cycles')->assertForbidden();
});

it('lists the cycles within the record scope of the reader', function (): void {
    $version = publishedAccreditedCriteria();
    $mine = draftAwardCycle($version);
    $other = draftAwardCycle($version);
    $secretary = awardSecretary();
    $officer = filteringOfficer();
    $assessor = assessorUser();
    addCycleMember($mine, $secretary, CycleMemberRole::AwardSecretary);
    addCycleMember($other, $officer, CycleMemberRole::FilteringOfficer);
    linkInitialAssessor($other, $assessor);

    switchTo(systemAdmin())->getJson('/api/v1/award/cycles')->assertOk()
        ->assertJsonPath('meta.pagination.total', 2)
        ->assertJsonPath('meta.stats.by_status.draft', 2);
    switchTo(requestFollower())->getJson('/api/v1/award/cycles')->assertOk()->assertJsonPath('meta.pagination.total', 2);
    switchTo($secretary)->getJson('/api/v1/award/cycles')->assertOk()->assertJsonPath('meta.pagination.total', 1)->assertJsonPath('data.0.id', $mine->id);
    switchTo($officer)->getJson('/api/v1/award/cycles')->assertOk()->assertJsonPath('meta.pagination.total', 1)->assertJsonPath('data.0.id', $other->id);
    switchTo($assessor)->getJson('/api/v1/award/cycles')->assertOk()->assertJsonPath('meta.pagination.total', 1);
    switchTo(awardSecretary())->getJson('/api/v1/award/cycles')->assertOk()->assertJsonPath('meta.pagination.total', 0);
    switchTo(systemAdmin())->getJson('/api/v1/award/cycles?filter[status]=closed')->assertOk()->assertJsonPath('meta.pagination.total', 0);
    test()->getJson('/api/v1/award/cycles?sort=name')->assertUnprocessable();

    switchTo($secretary)->getJson("/api/v1/award/cycles/{$other->id}")->assertNotFound();
    switchTo($officer)->getJson("/api/v1/award/cycles/{$other->id}")->assertOk()->assertJsonPath('data.can_manage', false)->assertJsonMissingPath('data.members');
    switchTo(requestFollower())->getJson("/api/v1/award/cycles/{$other->id}")->assertOk()->assertJsonPath('data.members.0.id', $officer->id)->assertJsonPath('data.initial_assessors.0.id', $assessor->id);
});

it('updates a draft cycle and locks fields by stage', function (): void {
    $version = publishedAccreditedCriteria();
    $draft = draftAwardCycle($version);
    $open = openAwardCycle($version);
    $admin = systemAdmin();

    switchTo($admin)->putJson("/api/v1/award/cycles/{$draft->id}", ['min_entity_age_years' => 4, 'participation_certificate_enabled' => true])->assertOk()
        ->assertJsonPath('data.conditions.min_entity_age_years', 4)
        ->assertJsonPath('data.participation_certificate_enabled', true);
    test()->putJson("/api/v1/award/cycles/{$draft->id}", ['committee_start' => $draft->submission_start->copy()->subDay()->toIso8601String()])
        ->assertUnprocessable()->assertJsonPath('code', 'cycle.dates_unordered');
    test()->putJson("/api/v1/award/cycles/{$draft->id}", ['status' => 'submission_open'])->assertUnprocessable()->assertJsonValidationErrors('status');

    test()->putJson("/api/v1/award/cycles/{$open->id}", ['submission_start' => '2026-10-01T06:00:00+00:00'])
        ->assertConflict()->assertJsonPath('code', 'cycle.field_locked')->assertJsonPath('meta.locked_fields', ['submission_start']);
    test()->putJson("/api/v1/award/cycles/{$open->id}", ['submission_end' => '2026-10-31T06:00:00+00:00'])
        ->assertUnprocessable()->assertJsonPath('code', 'cycle.dates_unordered');
    test()->putJson("/api/v1/award/cycles/{$open->id}", ['submission_end' => '2026-11-15T06:00:00+00:00'])->assertOk()
        ->assertJsonPath('data.submission_end', '2026-11-15T06:00:00+00:00');

    expect(Audit::query()->where('event', AuditEvent::CycleUpdated->value)->where('auditable_id', $open->id)->firstOrFail()->old_values)
        ->toHaveKey('submission_end');

    closeCycleSubmissions($open);
    test()->putJson("/api/v1/award/cycles/{$open->id}", ['min_entity_age_years' => 2])->assertConflict()->assertJsonPath('code', 'cycle.field_locked');
    test()->putJson("/api/v1/award/cycles/{$open->id}", ['initial_review_sla_days' => 21])->assertOk()->assertJsonPath('data.initial_review_sla_days', 21);
});

it('keeps the regions that already have requests', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    advancedDraft($cycle);
    $other = Region::factory()->create();

    switchTo(systemAdmin())->putJson("/api/v1/award/cycles/{$cycle->id}", ['region_ids' => [$other->id]])
        ->assertUnprocessable()->assertJsonPath('code', 'cycle.region_has_requests');
    test()->putJson("/api/v1/award/cycles/{$cycle->id}", ['region_ids' => [$cycle->regions->firstOrFail()->id, $other->id]])->assertOk()
        ->assertJsonCount(2, 'data.regions');
});

it('lets a secretary manage only the cycles they belong to', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = draftAwardCycle($version);
    $member = awardSecretary();
    addCycleMember($cycle, $member, CycleMemberRole::AwardSecretary);
    $officer = filteringOfficer();
    addCycleMember($cycle, $officer, CycleMemberRole::FilteringOfficer);

    switchTo(awardSecretary())->putJson("/api/v1/award/cycles/{$cycle->id}", ['year' => 2027])->assertNotFound();
    switchTo($officer)->putJson("/api/v1/award/cycles/{$cycle->id}", ['year' => 2027])->assertForbidden();
    switchTo($member)->putJson("/api/v1/award/cycles/{$cycle->id}", ['year' => 2027])->assertOk()->assertJsonPath('data.year', 2027);
});

it('requires a reason from the Super Admin', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = draftAwardCycle($version);
    $superAdmin = superAdmin();

    switchTo($superAdmin)->putJson("/api/v1/award/cycles/{$cycle->id}", ['year' => 2027])->assertUnprocessable()->assertJsonPath('code', 'reason_required');
    test()->putJson("/api/v1/award/cycles/{$cycle->id}", ['year' => 2027, 'reason' => SUPER_ADMIN_REASON])->assertOk();
});

it('assigns the filtering officers and secretaries of a cycle with the role and segregation checks', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = draftAwardCycle($version);
    $officer = filteringOfficer();
    $secretary = awardSecretary();
    $assessor = assessorUser();
    $both = staffUser(RoleKey::AwardSecretary, RoleKey::Assessor);
    linkInitialAssessor($cycle, $both);
    $admin = systemAdmin();

    switchTo($admin)->putJson("/api/v1/award/cycles/{$cycle->id}/members", ['filtering_officer_ids' => [$officer->id], 'award_secretary_ids' => [$secretary->id]])->assertOk()
        ->assertJsonCount(2, 'data.members');
    test()->putJson("/api/v1/award/cycles/{$cycle->id}/members", ['filtering_officer_ids' => [$assessor->id]])
        ->assertUnprocessable()->assertJsonPath('code', 'cycle.member_invalid')->assertJsonValidationErrors('filtering_officer_ids.0');
    test()->putJson("/api/v1/award/cycles/{$cycle->id}/members", ['award_secretary_ids' => [$secretary->id, $both->id]])
        ->assertUnprocessable()->assertJsonPath('code', 'sod_conflict')->assertJsonValidationErrors('award_secretary_ids.1');
    test()->putJson("/api/v1/award/cycles/{$cycle->id}/members", [])->assertUnprocessable();
    test()->putJson("/api/v1/award/cycles/{$cycle->id}/members", ['filtering_officer_ids' => []])->assertOk();

    expect(AwardCycleMember::query()->where('award_cycle_id', $cycle->id)->pluck('user_id')->all())->toBe([$secretary->id])
        ->and(Audit::query()->where('event', AuditEvent::CycleMembersChanged->value)->count())->toBe(2);
});

it('links the initial assessors of a cycle and keeps them apart from the cycle members', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = draftAwardCycle($version);
    $assessor = assessorUser();
    $memberAssessor = staffUser(RoleKey::FilteringOfficer, RoleKey::Assessor);
    addCycleMember($cycle, $memberAssessor, CycleMemberRole::FilteringOfficer);
    $admin = systemAdmin();

    switchTo($admin)->putJson("/api/v1/award/cycles/{$cycle->id}/initial-assessors", ['assessor_ids' => [$assessor->id]])->assertOk()
        ->assertJsonPath('data.initial_assessors.0.id', $assessor->id)
        ->assertJsonPath('data.initial_assessors.0.is_active', true);
    test()->putJson("/api/v1/award/cycles/{$cycle->id}/initial-assessors", ['assessor_ids' => [$assessor->id, $memberAssessor->id]])
        ->assertUnprocessable()->assertJsonPath('code', 'sod_conflict');
    test()->putJson("/api/v1/award/cycles/{$cycle->id}/initial-assessors", ['assessor_ids' => [requestFollower()->id]])
        ->assertUnprocessable()->assertJsonPath('code', 'cycle.initial_assessor_invalid');
    test()->putJson("/api/v1/award/cycles/{$cycle->id}/initial-assessors", ['assessor_ids' => []])->assertOk()
        ->assertJsonPath('data.initial_assessors.0.is_active', false);

    expect(AwardCycleAssessor::query()->where('award_cycle_id', $cycle->id)->active()->count())->toBe(0);
});

it('returns the default conditions and the staff candidates to the cycle managers', function (): void {
    $assessor = assessorUser();
    $officer = filteringOfficer();
    $admin = systemAdmin();

    switchTo($admin)->getJson('/api/v1/award/cycles/default-conditions')->assertOk()
        ->assertExactJson(['data' => ['min_entity_age_years' => 3, 'disqualification_score' => '60.00', 'initial_review_sla_days' => 14], 'meta' => [], 'errors' => null]);
    test()->getJson('/api/v1/award/cycles/candidates?role=initial_assessor')->assertOk()->assertJsonPath('data.0.id', $assessor->id)->assertJsonMissingPath('data.0.email');
    test()->getJson('/api/v1/award/cycles/candidates?role=filtering_officer')->assertOk()->assertJsonPath('data.0.id', $officer->id);
    test()->getJson('/api/v1/award/cycles/candidates?role=entity')->assertUnprocessable();

    switchTo(requestFollower())->getJson('/api/v1/award/cycles/candidates?role=initial_assessor')->assertForbidden();
    test()->getJson('/api/v1/award/cycles/default-conditions')->assertForbidden();
});

it('uses the assessment settings as the defaults of new cycles', function (): void {
    $admin = systemAdmin();

    switchTo($admin)->putJson('/api/v1/settings/assessment', [
        'advanced_min_entity_age_years' => 2,
        'advanced_disqualification_score' => '55.5',
        'advanced_initial_review_sla_days' => 21,
    ])->assertOk()
        ->assertJsonPath('data.advanced_min_entity_age_years', 2)
        ->assertJsonPath('data.advanced_disqualification_score', '55.50')
        ->assertJsonPath('data.advanced_initial_review_sla_days', 21);
    test()->putJson('/api/v1/settings/assessment', ['advanced_disqualification_score' => '101'])->assertUnprocessable();

    $version = publishedAccreditedCriteria();
    switchTo($admin)->postJson('/api/v1/award/cycles', cyclePayload($version, Region::factory()->create()))->assertCreated()
        ->assertJsonPath('data.conditions.min_entity_age_years', 2)
        ->assertJsonPath('data.conditions.disqualification_score', '55.50')
        ->assertJsonPath('data.initial_review_sla_days', 21);

    expect(AwardCycle::query()->where('status', AwardCycleStatus::Draft)->count())->toBe(1);
});

it('reserves the participation certificate setting of a cycle to the System Administrator', function (): void {
    $version = publishedAccreditedCriteria();
    $secretary = awardSecretary();
    $region = Region::factory()->create();

    switchTo($secretary)->postJson('/api/v1/award/cycles', cyclePayload($version, $region, ['participation_certificate_enabled' => true]))
        ->assertForbidden()->assertJsonPath('code', 'cycle.participation_setting_reserved');
    $id = test()->postJson('/api/v1/award/cycles', cyclePayload($version, $region))->assertCreated()->json('data.id');
    test()->putJson("/api/v1/award/cycles/{$id}", ['participation_certificate_enabled' => true])->assertForbidden();

    switchTo(systemAdmin())->putJson("/api/v1/award/cycles/{$id}", ['participation_certificate_enabled' => true])->assertOk()
        ->assertJsonPath('data.participation_certificate_enabled', true);
});
