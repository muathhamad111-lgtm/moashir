<?php

declare(strict_types=1);

use App\Enums\Entities\ProfileSection;
use App\Enums\Identity\EmailChangePolicy;
use App\Enums\Identity\EmailChangeStatus;
use App\Enums\Identity\RoleKey;
use App\Models\Entities\Entity;
use App\Models\Identity\EmailChangeRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

const TIMELINE_DEACTIVATION_REASON = 'توقف النشاط مؤقتًا بطلب من الجهة.';

const TIMELINE_LIFECYCLE_KINDS = [
    'created', 'verification_reminded', 'verified', 'approved', 'rejected', 'profile_completed',
    'marketing_consented', 'marketing_unsubscribed', 'deactivated', 'restored', 'deleted',
];

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-01 09:00:00');
});

function timelineEmailChange(Entity $entity, string $newEmail): EmailChangeRequest
{
    $owner = ownerOf($entity);

    return EmailChangeRequest::query()->create([
        'user_id' => $owner->id,
        'entity_id' => $entity->id,
        'old_email' => $owner->email,
        'new_email' => $newEmail,
        'policy' => EmailChangePolicy::RequiresApproval,
        'status' => EmailChangeStatus::AwaitingApproval,
    ]);
}

/**
 * An entity whose profile change was requested at 10:00 and approved at 11:00, whose owner asked to change the
 * e-mail at 12:00, and which staff deactivated at 13:00.
 */
function timelineEntityWithHistory(): Entity
{
    $entity = entityWithOwner();
    Date::setTestNow('2026-11-01 10:00:00');
    saveSection($entity, ProfileSection::Governance, ['employees_count' => 3])->assertAccepted();
    Date::setTestNow('2026-11-01 11:00:00');
    approveProfileChange($entity)->assertOk();
    Date::setTestNow('2026-11-01 12:00:00');
    timelineEmailChange($entity, 'new-mail@alamal.example.sa');
    Date::setTestNow('2026-11-01 13:00:00');
    $entity->forceFill(['deactivated_at' => Date::now(), 'deactivation_reason' => TIMELINE_DEACTIVATION_REASON])->save();

    return $entity;
}

/**
 * @return list<string>
 */
function timelineKinds(Entity $entity, string $query = ''): array
{
    $kinds = test()->getJson("/api/v1/entities/{$entity->id}/timeline{$query}")->assertOk()->json('data.*.kind');

    return is_array($kinds) ? array_values($kinds) : [];
}

it('lists the account events, profile changes and e-mail changes newest first', function (): void {
    $entity = timelineEntityWithHistory();
    switchTo(systemAdmin());

    $response = $this->getJson("/api/v1/entities/{$entity->id}/timeline?per_page=100")
        ->assertOk()
        ->assertJsonPath('data.0.kind', 'deactivated')
        ->assertJsonPath('data.0.occurred_at', '2026-11-01T13:00:00+00:00')
        ->assertJsonPath('data.0.reason', TIMELINE_DEACTIVATION_REASON)
        ->assertJsonPath('data.1.kind', 'email_change_requested')
        ->assertJsonPath('data.1.email.new', 'new-mail@alamal.example.sa')
        ->assertJsonPath('data.1.status', null);

    $entries = collect($response->json('data'));
    $decided = $entries->firstWhere('kind', 'profile_change_decided');
    $changed = $entries->firstWhere('kind', 'profile_field_changed');
    $requested = $entries->firstWhere('kind', 'profile_change_requested');

    expect($decided['status'])->toBe('approved')
        ->and($decided['occurred_at'])->toBe('2026-11-01T11:00:00+00:00')
        ->and($decided['fields'])->toContain('employees_count')
        ->and($decided['actor']['name'])->not->toBeNull()
        ->and($changed['change']['field'])->toBe('employees_count')
        ->and($changed['change']['new_value'])->toBe(3)
        ->and($requested['occurred_at'])->toBe('2026-11-01T10:00:00+00:00')
        ->and($requested['actor'])->toBeNull()
        ->and($entries->last()['kind'])->toBe('created')
        ->and($entries->pluck('occurred_at')->all())->toBe($entries->pluck('occurred_at')->sortDesc()->values()->all());
});

it('pages the feed in the same newest-first order', function (): void {
    $entity = timelineEntityWithHistory();
    switchTo(systemAdmin());
    $all = timelineKinds($entity, '?per_page=100');

    $this->getJson("/api/v1/entities/{$entity->id}/timeline?per_page=2")
        ->assertOk()
        ->assertJsonPath('meta.pagination.per_page', 2)
        ->assertJsonPath('meta.pagination.total', count($all))
        ->assertJsonPath('meta.pagination.last_page', (int) ceil(count($all) / 2));

    $paged = [...timelineKinds($entity, '?per_page=2&page=1'), ...timelineKinds($entity, '?per_page=2&page=2')];

    expect($paged)->toBe(array_slice($all, 0, 4));
});

it('keeps the changes to the full record scope and shows the lifecycle events to contact-only staff', function (): void {
    $entity = timelineEntityWithHistory();
    switchTo(staffUser(RoleKey::FinanceOfficer));

    $response = $this->getJson("/api/v1/entities/{$entity->id}/timeline?per_page=100")->assertOk();

    expect($response->json('data.*.kind'))->each->toBeIn(TIMELINE_LIFECYCLE_KINDS)
        ->and($response->getContent())->not->toContain('new-mail@alamal.example.sa');
});

it('never shows another entity events', function (): void {
    $entity = timelineEntityWithHistory();
    $other = entityWithOwner();
    timelineEmailChange($other, 'other-mail@albirr.example.sa');
    saveSection($other, ProfileSection::Governance, ['employees_count' => 9])->assertAccepted();
    switchTo(systemAdmin());

    $response = $this->getJson("/api/v1/entities/{$entity->id}/timeline?per_page=100")->assertOk();

    expect($response->getContent())->not->toContain('other-mail@albirr.example.sa')
        ->and(collect($response->json('data'))->where('kind', 'profile_change_requested'))->toHaveCount(1);
});

it('denies the timeline to staff who may not view the entity', function (RoleKey $role, int $status): void {
    $entity = entityWithOwner();

    switchTo(staffUser($role))->getJson("/api/v1/entities/{$entity->id}/timeline")->assertStatus($status);
})->with([
    'criteria officer, no entities.view' => [RoleKey::CriteriaOfficer, 403],
    'assessor, no entity in scope' => [RoleKey::Assessor, 404],
]);

it('answers 404 for an unknown entity and 401 without a session', function (): void {
    switchTo(systemAdmin())->getJson('/api/v1/entities/999999/timeline')->assertNotFound();
    $this->flushSession();
    app('auth')->forgetGuards();
    $this->getJson('/api/v1/entities/1/timeline')->assertUnauthorized();
});

it('rejects an invalid page size', function (): void {
    $entity = entityWithOwner();

    switchTo(systemAdmin())->getJson("/api/v1/entities/{$entity->id}/timeline?per_page=500")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');
});
