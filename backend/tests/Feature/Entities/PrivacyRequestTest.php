<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Models\Audit\Audit;
use App\Models\Entities\PrivacyRequest;
use App\Models\Identity\Role;
use Spatie\Permission\PermissionRegistrar;

const PRIVACY_DETAILS = 'طلبت الجهة نسخة من بياناتها الشخصية المحفوظة في المنصة.';

it('registers a data-subject request and audits it', function (): void {
    $entity = entityWithOwner();
    $admin = systemAdmin();
    signIn($admin);

    $this->postJson('/api/v1/privacy/requests', ['entity_id' => $entity->id, 'kind' => 'access', 'details' => PRIVACY_DETAILS])
        ->assertCreated()
        ->assertJsonPath('data.kind', 'access')
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.entity.id', $entity->id)
        ->assertJsonPath('data.created_by.id', $admin->id);

    expect(Audit::query()->where('event', AuditEvent::PrivacyRequestCreated->value)->exists())->toBeTrue();
});

it('lists and filters data-subject requests', function (): void {
    $entity = entityWithOwner();
    $admin = systemAdmin();
    PrivacyRequest::query()->create(['entity_id' => $entity->id, 'kind' => 'access', 'details' => PRIVACY_DETAILS, 'created_by_user_id' => $admin->id]);
    PrivacyRequest::query()->create(['entity_id' => $entity->id, 'kind' => 'deletion', 'details' => PRIVACY_DETAILS, 'created_by_user_id' => $admin->id]);
    signIn($admin);

    $this->getJson('/api/v1/privacy/requests')->assertOk()->assertJsonPath('meta.pagination.total', 2);
    $this->getJson('/api/v1/privacy/requests?filter[kind]=deletion')->assertOk()->assertJsonPath('meta.pagination.total', 1)->assertJsonPath('data.0.kind', 'deletion');
    $this->getJson('/api/v1/privacy/requests?filter[kind]=erase')->assertUnprocessable();
});

it('fulfils and rejects a data-subject request once', function (): void {
    $entity = entityWithOwner();
    $admin = systemAdmin();
    $fulfilled = PrivacyRequest::query()->create(['entity_id' => $entity->id, 'kind' => 'correction', 'details' => PRIVACY_DETAILS, 'created_by_user_id' => $admin->id]);
    $rejected = PrivacyRequest::query()->create(['entity_id' => $entity->id, 'kind' => 'deletion', 'details' => PRIVACY_DETAILS, 'created_by_user_id' => $admin->id]);
    signIn($admin);

    $this->getJson("/api/v1/privacy/requests/{$fulfilled->id}")->assertOk()->assertJsonPath('data.details', PRIVACY_DETAILS);
    $this->postJson("/api/v1/privacy/requests/{$fulfilled->id}/fulfil", ['reason' => 'صُحّحت البيانات وفق المستندات المرفقة.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'fulfilled')
        ->assertJsonPath('data.decided_by.id', $admin->id);
    $this->postJson("/api/v1/privacy/requests/{$fulfilled->id}/reject", ['reason' => 'قرار مكرر على الطلب نفسه.'])->assertConflict();

    $this->postJson("/api/v1/privacy/requests/{$rejected->id}/reject", [])->assertUnprocessable()->assertJsonValidationErrors(['reason']);
    $this->postJson("/api/v1/privacy/requests/{$rejected->id}/reject", ['reason' => 'يتعارض الحذف مع مدة الاحتفاظ بالسجلات المالية.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    expect(Audit::query()->where('event', AuditEvent::PrivacyRequestFulfilled->value)->count())->toBe(1)
        ->and(Audit::query()->where('event', AuditEvent::PrivacyRequestRejected->value)->value('reason'))->toBe('يتعارض الحذف مع مدة الاحتفاظ بالسجلات المالية.');
});

it('validates new data-subject requests', function (Closure $mutate, string $field): void {
    $entity = entityWithOwner();
    signIn(systemAdmin());

    $this->postJson('/api/v1/privacy/requests', $mutate(['entity_id' => $entity->id, 'kind' => 'access', 'details' => PRIVACY_DETAILS]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'unknown entity' => [static fn (array $body): array => [...$body, 'entity_id' => 999999], 'entity_id'],
    'unknown kind' => [static fn (array $body): array => [...$body, 'kind' => 'erase'], 'kind'],
    'details too short' => [static fn (array $body): array => [...$body, 'details' => 'قصير'], 'details'],
]);

it('exports the personal data of an entity for an access request and audits the export', function (): void {
    $entity = entityWithOwner();
    $owner = ownerOf($entity);
    $terms = publishTerms();
    $owner->termsAcceptances()->create(['terms_version_id' => $terms->id]);
    signIn(systemAdmin());

    $this->postJson("/api/v1/entities/{$entity->id}/export", ['reason' => 'تنفيذ طلب الاطلاع على البيانات الشخصية رقم 14.'])
        ->assertOk()
        ->assertJsonPath('data.entity.reference', $entity->reference())
        ->assertJsonPath('data.account.official_email', $owner->email)
        ->assertJsonPath('data.consents.terms.0.version', '2026-11')
        ->assertJsonStructure(['data' => ['generated_at', 'entity', 'account', 'consents', 'profile_changes', 'email_changes', 'privacy_requests']])
        ->assertJsonMissingPath('data.account.password');

    expect(Audit::query()->where('event', AuditEvent::PrivacyDataExported->value)->value('reason'))->toBe('تنفيذ طلب الاطلاع على البيانات الشخصية رقم 14.');
});

it('requires a reason for the personal data export', function (): void {
    $entity = entityWithOwner();
    signIn(systemAdmin());

    $this->postJson("/api/v1/entities/{$entity->id}/export", [])->assertUnprocessable()->assertJsonValidationErrors(['reason']);
});

it('keeps data-subject tooling behind its own permission', function (RoleKey $role, string $method, string $uri): void {
    $entity = entityWithOwner();
    signIn(staffUser($role));

    $this->json($method, str_replace('{id}', (string) $entity->id, $uri), ['reason' => 'تنفيذ طلب الاطلاع على البيانات.'])->assertForbidden();
})->with([RoleKey::RequestFollower, RoleKey::FinanceOfficer])->with([
    ['GET', '/api/v1/privacy/requests'],
    ['POST', '/api/v1/privacy/requests'],
    ['POST', '/api/v1/entities/{id}/export'],
]);

it('grants the data-subject tooling to a role through the matrix', function (): void {
    Role::findByName(RoleKey::FinanceOfficer->value)->givePermissionTo(PermissionKey::PrivacyRequestsManage->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    signIn(staffUser(RoleKey::FinanceOfficer));

    $this->getJson('/api/v1/privacy/requests')->assertOk();
});
