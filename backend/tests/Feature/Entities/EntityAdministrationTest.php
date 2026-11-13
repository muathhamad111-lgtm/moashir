<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Entities\EntityStatus;
use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Models\Audit\Audit;
use App\Models\Entities\Entity;
use App\Models\Entities\EntityProfileChange;
use App\Models\Identity\Role;
use App\Models\Identity\UserSession;
use App\Notifications\Entities\EntityNotification;
use App\Notifications\Identity\OtpCodeNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    Notification::fake();
});

function grantToRole(RoleKey $role, PermissionKey $permission): void
{
    Role::findByName($role->value)->givePermissionTo($permission->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

it('lists entities with filters, pagination and status counters for the system administrator', function (): void {
    $active = entityWithOwner(static fn ($factory) => $factory->named('جمعية الأمل الخيرية', 'Al-Amal Charitable Association'));
    entityWithOwner(static fn ($factory) => $factory->pendingVerification()->named('مؤسسة النماء الأهلية'));
    entityWithOwner(static fn ($factory) => $factory->deactivated());
    signIn(systemAdmin());

    $this->getJson('/api/v1/entities?per_page=2&sort=name')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 3)
        ->assertJsonPath('meta.pagination.per_page', 2)
        ->assertJsonPath('meta.stats.total', 3)
        ->assertJsonPath('meta.stats.active', 1)
        ->assertJsonPath('meta.stats.pending_verification', 1)
        ->assertJsonPath('meta.stats.deactivated', 1)
        ->assertJsonCount(2, 'data');

    $this->getJson('/api/v1/entities?filter[status]=active&filter[region_id]='.$active->region_id)
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.id', $active->id)
        ->assertJsonPath('data.0.official_email', ownerOf($active)->email)
        ->assertJsonPath('data.0.is_frozen', false);
});

it('searches entities by Arabic name without regard to hamza forms or diacritics, and by e-mail', function (string $term): void {
    $target = entityWithOwner(static fn ($factory) => $factory->named('جمعية الأمل الخيرية', 'Al-Amal Charitable Association'));
    ownerOf($target)->forceFill(['email' => 'contact@alamal.example.sa'])->save();
    entityWithOwner(static fn ($factory) => $factory->named('مؤسسة النماء الأهلية', 'Al-Namaa Civil Foundation'));
    signIn(systemAdmin());

    $this->getJson('/api/v1/entities?search='.urlencode($term))
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.id', $target->id);
})->with([
    'without hamza' => ['الامل'],
    'with diacritics' => ['الأَمَل'],
    'english name' => ['al-amal'],
    'e-mail' => ['CONTACT@ALAMAL'],
]);

it('hides deleted and erased entities from the operational list', function (): void {
    entityWithOwner();
    $deleted = entityWithOwner();
    $deleted->delete();
    signIn(systemAdmin());

    $this->getJson('/api/v1/entities')->assertOk()->assertJsonPath('meta.pagination.total', 1);
    $this->getJson('/api/v1/entities?filter[trashed]=only')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.id', $deleted->id)
        ->assertJsonPath('data.0.is_frozen', true);
});

it('limits the finance officer to name and contact fields', function (): void {
    $entity = entityWithOwner();
    signIn(staffUser(RoleKey::FinanceOfficer));

    $this->getJson('/api/v1/entities')
        ->assertOk()
        ->assertJsonPath('data.0.official_email', ownerOf($entity)->email)
        ->assertJsonMissingPath('data.0.governance_grade')
        ->assertJsonMissingPath('data.0.deactivation_reason');
    $this->getJson("/api/v1/entities/{$entity->id}")->assertOk()->assertJsonMissingPath('data.employees_count');
});

it('shows no entity to roles whose scope is limited to their own assignments', function (RoleKey $role): void {
    $entity = entityWithOwner();
    signIn(staffUser($role));

    $this->getJson('/api/v1/entities')->assertOk()->assertJsonPath('meta.pagination.total', 0);
    $this->getJson("/api/v1/entities/{$entity->id}")->assertNotFound();
})->with([RoleKey::Assessor, RoleKey::FilteringOfficer, RoleKey::AwardSecretary]);

it('denies the entities list to roles without the permission', function (RoleKey $role): void {
    signIn($role === RoleKey::Entity ? ownerOf(entityWithOwner()) : staffUser($role));

    $this->getJson('/api/v1/entities')->assertForbidden();
})->with([RoleKey::CriteriaOfficer, RoleKey::ContentOfficer, RoleKey::Entity]);

it('denies the deleted entities section without the delete permission', function (): void {
    signIn(staffUser(RoleKey::RequestFollower));

    $this->getJson('/api/v1/entities?filter[trashed]=only')->assertForbidden();
});

it('validates the list parameters', function (string $query): void {
    signIn(systemAdmin());

    $this->getJson('/api/v1/entities?'.$query)->assertUnprocessable()->assertJsonPath('code', 'validation_failed');
})->with([
    'unknown status' => ['filter[status]=closed'],
    'unknown type' => ['filter[entity_type]=family_fund'],
    'unknown filter' => ['filter[name]=x'],
    'unknown sort' => ['sort=password'],
    'page size too big' => ['per_page=500'],
    'trashed value' => ['filter[trashed]=with'],
]);

it('returns the staff view of one entity', function (): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->deactivated());
    signIn(systemAdmin());

    $this->getJson("/api/v1/entities/{$entity->id}")
        ->assertOk()
        ->assertJsonPath('data.reference', $entity->reference())
        ->assertJsonPath('data.status', 'deactivated')
        ->assertJsonPath('data.deactivation_reason', $entity->deactivation_reason);
});

it('answers 404 for an unknown or erased entity', function (): void {
    $erased = entityWithOwner();
    $erased->forceFill(['deleted_at' => now(), 'anonymized_at' => now()])->save();
    signIn(systemAdmin());

    $this->getJson('/api/v1/entities/999999')->assertNotFound()->assertJsonPath('code', 'not_found');
    $this->getJson("/api/v1/entities/{$erased->id}")->assertNotFound();
});

it('edits core data on behalf of the entity with a mandatory reason and logs the change', function (): void {
    $entity = entityWithOwner();
    $admin = systemAdmin();
    signIn($admin);

    $this->putJson("/api/v1/entities/{$entity->id}", [...profilePayload($entity, ['annual_beneficiaries_count' => 12000]), 'reason' => 'تصحيح عدد المستفيدين بناءً على تقرير الجهة السنوي.'])
        ->assertOk()
        ->assertJsonPath('data.annual_beneficiaries_count', 12000);

    expect(EntityProfileChange::query()->where('entity_id', $entity->id)->where('changed_by_user_id', $admin->id)->exists())->toBeTrue()
        ->and(Audit::query()->where('event', AuditEvent::EntityUpdatedByStaff->value)->value('reason'))->toBe('تصحيح عدد المستفيدين بناءً على تقرير الجهة السنوي.');
    Notification::assertSentTo(ownerOf($entity), EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::EntityCoreDataEdited);
});

it('requires a reason to edit on behalf', function (): void {
    $entity = entityWithOwner();
    signIn(systemAdmin());

    $this->putJson("/api/v1/entities/{$entity->id}", profilePayload($entity))->assertUnprocessable()->assertJsonValidationErrors(['reason']);
});

it('requires the manage permission to edit on behalf', function (RoleKey $role): void {
    $entity = entityWithOwner();
    signIn(staffUser($role));

    $this->putJson("/api/v1/entities/{$entity->id}", [...profilePayload($entity), 'reason' => 'تحديث البيانات الأساسية للجهة.'])->assertForbidden();
})->with([RoleKey::RequestFollower, RoleKey::FinanceOfficer]);

it('deactivates an active entity with a reason, ends its sessions and notifies it', function (): void {
    $entity = entityWithOwner();
    $owner = ownerOf($entity);
    UserSession::query()->forceCreate(['id' => 'entity-session', 'user_id' => $owner->id, 'payload' => '', 'last_activity' => now()->getTimestamp()]);
    signIn(systemAdmin());

    $this->postJson("/api/v1/entities/{$entity->id}/deactivate", ['reason' => 'طلب مجلس إدارة الجهة إيقاف الحساب مؤقتًا.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'deactivated');

    expect($entity->refresh()->status)->toBe(EntityStatus::Deactivated)
        ->and(UserSession::query()->where('user_id', $owner->id)->count())->toBe(0)
        ->and(Audit::query()->where('event', AuditEvent::EntityDeactivated->value)->value('reason'))->toBe('طلب مجلس إدارة الجهة إيقاف الحساب مؤقتًا.');
    Notification::assertSentTo($owner, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::EntityDeactivated);
});

it('blocks the sign-in of a deactivated entity', function (): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->deactivated());
    $owner = ownerOf($entity);

    signInWith($owner->email, defaultPassword())
        ->assertForbidden()
        ->assertJsonPath('code', 'account_deactivated');

    Notification::assertNotSentTo($owner, OtpCodeNotification::class);
});

it('reactivates a deactivated entity with a reason', function (): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->deactivated());
    signIn(systemAdmin());

    $this->postJson("/api/v1/entities/{$entity->id}/reactivate", ['reason' => 'استكملت الجهة متطلبات إعادة التفعيل.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.deactivation_reason', null);

    Notification::assertSentTo(ownerOf($entity), EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::EntityReactivated);
});

it('rejects illegal account transitions with 409', function (string $state, string $verb): void {
    $entity = entityWithOwner(static fn ($factory) => $state === 'active' ? $factory : $factory->{$state}());
    signIn(systemAdmin());

    $this->postJson("/api/v1/entities/{$entity->id}/{$verb}", ['reason' => 'سبب موثق للإجراء الإداري.'])
        ->assertConflict()
        ->assertJsonPath('code', 'state.transition_illegal');
})->with([
    'reactivate an active entity' => ['active', 'reactivate'],
    'deactivate a deactivated entity' => ['deactivated', 'deactivate'],
    'deactivate an unverified entity' => ['pendingVerification', 'deactivate'],
]);

it('requires a reason to deactivate or reactivate', function (string $verb): void {
    $entity = entityWithOwner();
    signIn(systemAdmin());

    $this->postJson("/api/v1/entities/{$entity->id}/{$verb}", ['reason' => 'قصير'])->assertUnprocessable()->assertJsonValidationErrors(['reason']);
})->with(['deactivate', 'reactivate']);

it('treats deactivation and reactivation as independent grantable permissions', function (): void {
    $entity = entityWithOwner();
    grantToRole(RoleKey::RequestFollower, PermissionKey::EntitiesDeactivate);
    signIn(staffUser(RoleKey::RequestFollower));

    $this->postJson("/api/v1/entities/{$entity->id}/deactivate", ['reason' => 'إيقاف مؤقت بطلب من الجهة.'])->assertOk();
    $this->postJson("/api/v1/entities/{$entity->id}/reactivate", ['reason' => 'إعادة تفعيل بطلب من الجهة.'])->assertForbidden();
});

it('denies account actions to staff without the independent permissions', function (string $verb): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->pendingVerification());
    signIn(staffUser(RoleKey::RequestFollower));

    $this->postJson("/api/v1/entities/{$entity->id}/{$verb}", ['reason' => 'سبب موثق للإجراء الإداري.'])->assertForbidden();
})->with(['deactivate', 'reactivate', 'verification/resend']);

it('re-sends the verification e-mail of an unverified registration and enforces a cooldown', function (): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->pendingVerification());
    signIn(systemAdmin());

    $this->postJson("/api/v1/entities/{$entity->id}/verification/resend")->assertAccepted()->assertJsonStructure(['data' => ['verification_reminded_at']]);
    $this->postJson("/api/v1/entities/{$entity->id}/verification/resend")
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'entity.verification_cooldown')
        ->assertHeader('Retry-After');

    $this->travel(16)->minutes();
    $this->postJson("/api/v1/entities/{$entity->id}/verification/resend")->assertAccepted();

    expect(Audit::query()->where('event', AuditEvent::EntityVerificationResent->value)->count())->toBe(2)
        ->and(Entity::query()->whereKey($entity->id)->exists())->toBeTrue();
    Notification::assertSentToTimes(ownerOf($entity), EntityNotification::class, 2);
});

it('refuses to re-send the verification of a verified entity', function (): void {
    $entity = entityWithOwner();
    signIn(systemAdmin());

    $this->postJson("/api/v1/entities/{$entity->id}/verification/resend")->assertConflict()->assertJsonPath('code', 'state.transition_illegal');
});

it('requires authentication for every staff entity endpoint', function (string $method, string $uri): void {
    $entity = entityWithOwner();

    $this->json($method, str_replace('{id}', (string) $entity->id, $uri))->assertUnauthorized();
})->with([
    ['GET', '/api/v1/entities'],
    ['GET', '/api/v1/entities/{id}'],
    ['PUT', '/api/v1/entities/{id}'],
    ['POST', '/api/v1/entities/{id}/deactivate'],
    ['POST', '/api/v1/entities/{id}/reactivate'],
    ['POST', '/api/v1/entities/{id}/verification/resend'],
    ['GET', '/api/v1/entities/{id}/deletion-impact'],
    ['DELETE', '/api/v1/entities/{id}'],
    ['POST', '/api/v1/entities/{id}/restore'],
    ['DELETE', '/api/v1/entities/{id}/permanent'],
    ['POST', '/api/v1/entities/export'],
    ['POST', '/api/v1/entities/{id}/export'],
]);
