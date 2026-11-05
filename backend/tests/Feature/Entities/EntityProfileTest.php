<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Entities\EntityProfileField;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Exceptions\Persistence\AppendOnlyViolation;
use App\Models\Audit\Audit;
use App\Models\Entities\EntityProfileChange;
use App\Notifications\Entities\EntityNotification;
use Database\Factories\Entities\EntityFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

it('returns the own entity profile to its single user', function (): void {
    $entity = entityWithOwner();
    $owner = ownerOf($entity);
    signIn($owner);

    $this->getJson('/api/v1/entities/me')
        ->assertOk()
        ->assertJsonPath('data.id', $entity->id)
        ->assertJsonPath('data.reference', $entity->reference())
        ->assertJsonPath('data.name', $entity->name->toArray())
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.official_email', $owner->email)
        ->assertJsonPath('data.authorized_person_name', $owner->name)
        ->assertJsonPath('data.region.id', $entity->region_id)
        ->assertJsonPath('data.city.id', $entity->city_id)
        ->assertJsonPath('data.governance_grade', '82.50')
        ->assertJsonPath('data.profile_completed', true)
        ->assertJsonPath('data.pending_email_change', null)
        ->assertJsonMissingPath('data.deactivation_reason')
        ->assertJsonMissingPath('data.deletion_reason');
});

it('denies the own profile to staff', function (): void {
    signIn(systemAdmin());

    $this->getJson('/api/v1/entities/me')->assertForbidden()->assertJsonPath('code', 'forbidden');
});

it('requires a session for the own profile', function (): void {
    $this->getJson('/api/v1/entities/me')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
});

it('updates the profile, records every changed field and notifies the administration', function (): void {
    $admin = systemAdmin();
    $entity = entityWithOwner(static fn (EntityFactory $factory): EntityFactory => $factory->state(['employees_count' => 40]));
    $owner = ownerOf($entity);
    signIn($owner);

    $this->putJson('/api/v1/entities/me', [...profilePayload($entity, ['governance_grade' => '91.5', 'employees_count' => 60]), 'marketing_consent' => true])
        ->assertOk()
        ->assertJsonPath('data.governance_grade', '91.50')
        ->assertJsonPath('data.employees_count', 60)
        ->assertJsonPath('data.marketing_consent', true)
        ->assertJsonPath('data.phone', '+966551234567');

    $fields = EntityProfileChange::query()->where('entity_id', $entity->id)->get()->keyBy(static fn (EntityProfileChange $change): string => $change->field->value);

    expect($fields->get(EntityProfileField::GovernanceGrade->value)?->old_value)->toBe('82.50')
        ->and($fields->get(EntityProfileField::GovernanceGrade->value)?->new_value)->toBe('91.50')
        ->and($fields->get(EntityProfileField::GovernanceGrade->value)?->changed_by_user_id)->toBe($owner->id)
        ->and($fields->has(EntityProfileField::EmployeesCount->value))->toBeTrue()
        ->and(Audit::query()->where('event', AuditEvent::EntityProfileUpdated->value)->count())->toBe(1);
    Notification::assertSentTo($admin, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::EntityCoreDataEdited);
    Notification::assertNotSentTo($owner, EntityNotification::class);
});

it('keeps the change history append-only', function (): void {
    $entity = entityWithOwner();
    signIn(ownerOf($entity));
    $this->putJson('/api/v1/entities/me', [...profilePayload($entity, ['employees_count' => 99]), 'marketing_consent' => false])->assertOk();

    $change = EntityProfileChange::query()->firstOrFail();

    expect(fn () => $change->delete())->toThrow(AppendOnlyViolation::class)
        ->and(fn () => DB::table('entity_profile_changes')->delete())->toThrow(QueryException::class)
        ->and(fn () => DB::table('entity_profile_changes')->update(['field' => 'name']))->toThrow(QueryException::class);
});

it('records the withdrawal of marketing consent', function (): void {
    $entity = entityWithOwner();
    $entity->forceFill(['marketing_consent_at' => now()])->save();
    signIn(ownerOf($entity));

    $this->putJson('/api/v1/entities/me', [...profilePayload($entity), 'marketing_consent' => false])->assertOk()->assertJsonPath('data.marketing_consent', false);

    expect($entity->refresh()->marketing_consent_at)->toBeNull()
        ->and($entity->marketing_unsubscribed_at)->not->toBeNull();
});

it('lets the entity move to another region and one of its cities', function (): void {
    $entity = entityWithOwner();
    $newCity = regionWithCity();
    signIn(ownerOf($entity));

    $this->putJson('/api/v1/entities/me', [...profilePayload($entity, ['region_id' => $newCity->region_id, 'city_id' => $newCity->id]), 'marketing_consent' => false])
        ->assertOk()
        ->assertJsonPath('data.region.id', $newCity->region_id)
        ->assertJsonPath('data.city.id', $newCity->id);
});

it('rejects invalid profile data', function (Closure $mutate, string $field): void {
    $entity = entityWithOwner();
    signIn(ownerOf($entity));

    $this->putJson('/api/v1/entities/me', $mutate([...profilePayload($entity), 'marketing_consent' => false]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'city of another region' => [static fn (array $payload): array => [...$payload, 'city_id' => regionWithCity()->id], 'city_id'],
    'governance above 100' => [static fn (array $payload): array => [...$payload, 'governance_grade' => 101], 'governance_grade'],
    'unknown type' => [static fn (array $payload): array => [...$payload, 'entity_type' => 'family_fund'], 'entity_type'],
    'missing consent answer' => [static fn (array $payload): array => array_diff_key($payload, ['marketing_consent' => true]), 'marketing_consent'],
    'missing person name' => [static fn (array $payload): array => [...$payload, 'authorized_person_name' => ''], 'authorized_person_name'],
]);

it('does not let a staff member use the own profile update', function (RoleKey $role): void {
    signIn(staffUser($role));

    $this->putJson('/api/v1/entities/me', [])->assertForbidden();
})->with([RoleKey::SystemAdmin, RoleKey::FinanceOfficer]);

it('keeps the authorized person contact data out of the audit log', function (): void {
    $entity = entityWithOwner();
    signIn(ownerOf($entity));

    $this->putJson('/api/v1/entities/me', [...profilePayload($entity, ['authorized_person_name' => 'ريم بنت خالد الحربي', 'phone' => '0569876543']), 'marketing_consent' => false])
        ->assertOk()
        ->assertJsonPath('data.authorized_person_name', 'ريم بنت خالد الحربي')
        ->assertJsonPath('data.phone', '+966569876543');

    $everyAudit = json_encode(Audit::query()->get(['old_values', 'new_values'])->toArray(), JSON_UNESCAPED_UNICODE);

    expect($everyAudit)->not->toContain('ريم بنت خالد الحربي')->not->toContain('569876543');
});
