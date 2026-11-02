<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Entities\EntityRequestPath;
use App\Enums\Entities\EntityStatus;
use App\Enums\Identity\EmailChangePolicy;
use App\Enums\Identity\EmailChangeStatus;
use App\Enums\Identity\RoleKey;
use App\Enums\Identity\TermsKind;
use App\Enums\Identity\UserStatus;
use App\Enums\Notifications\NotificationCode;
use App\Exceptions\Entities\EntityRejected;
use App\Models\Audit\Audit;
use App\Models\Entities\Entity;
use App\Models\Entities\EntityProfileChange;
use App\Models\Entities\PrivacyRequest;
use App\Models\Identity\AuthenticationEvent;
use App\Models\Identity\EmailChangeRequest;
use App\Models\Identity\OtpCode;
use App\Models\Identity\TermsAcceptance;
use App\Models\Identity\User;
use App\Models\Identity\UserSession;
use App\Notifications\Entities\EntityNotification;
use App\Notifications\Identity\SecurityAlertNotification;
use App\Services\Entities\EntityFreezeGuard;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

const DELETION_REASON = 'طلبت الجهة إغلاق حسابها بخطاب رسمي من مجلس الإدارة.';

beforeEach(function (): void {
    Notification::fake();
});

it('reports no open requests and the records kept on permanent deletion', function (): void {
    $entity = entityWithOwner();
    signIn(systemAdmin());

    $this->getJson("/api/v1/entities/{$entity->id}/deletion-impact")
        ->assertOk()
        ->assertJsonPath('data.open_requests', [])
        ->assertJsonPath('data.open_requests_count', 0)
        ->assertJsonPath('data.requires_confirmation', false)
        ->assertJsonPath('data.kept_on_permanent_deletion', [
            'payments_receipts_refunds', 'audit_log', 'anonymized_results', 'certificate_verification_records', 'consent_records', 'security_events',
        ]);
});

it('lists the open requests reported by the other domains', function (): void {
    $entity = entityWithOwner();
    giveOpenRequests($entity, [openRequest('ACC-2026-000014'), openRequest('BAG-2026-000003', EntityRequestPath::Bag, 3)]);
    signIn(systemAdmin());

    $this->getJson("/api/v1/entities/{$entity->id}/deletion-impact")
        ->assertOk()
        ->assertJsonPath('data.open_requests_count', 2)
        ->assertJsonPath('data.requires_confirmation', true)
        ->assertJsonPath('data.open_requests.0', ['id' => 1, 'number' => 'ACC-2026-000014', 'path' => 'accredited', 'status' => 'under_review', 'cycle_id' => null, 'is_frozen' => false])
        ->assertJsonPath('data.open_requests.1.path', 'bag');
});

it('soft deletes an entity without open requests, ends its sessions and audits it', function (): void {
    $admin = systemAdmin();
    $otherAdmin = systemAdmin();
    $entity = entityWithOwner();
    $owner = ownerOf($entity);
    UserSession::query()->forceCreate(['id' => 'entity-session', 'user_id' => $owner->id, 'payload' => '', 'last_activity' => now()->getTimestamp()]);
    signIn($admin);

    $this->deleteJson("/api/v1/entities/{$entity->id}", ['reason' => DELETION_REASON])->assertNoContent();

    $entity->refresh();
    expect($entity->trashed())->toBeTrue()
        ->and($entity->deleted_by_user_id)->toBe($admin->id)
        ->and($entity->deletion_reason)->toBe(DELETION_REASON)
        ->and($entity->status)->toBe(EntityStatus::Active)
        ->and(UserSession::query()->where('user_id', $owner->id)->count())->toBe(0);

    $audit = Audit::query()->where('event', AuditEvent::EntitySoftDeleted->value)->firstOrFail();
    expect($audit->reason)->toBe(DELETION_REASON)
        ->and($audit->new_values)->toMatchArray(['deleted' => true, 'frozen_request_numbers' => []]);

    Notification::assertSentTo($otherAdmin, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::EntityDeletionChanged);
    Notification::assertSentTo($owner, EntityNotification::class, static fn (EntityNotification $notification, array $channels): bool => $notification->notice->code === NotificationCode::EntityDeletionChanged && $channels === ['mail']);
    Notification::assertNotSentTo($admin, EntityNotification::class);
});

it('refuses to delete an entity with open requests until the deleter confirms the listed requests', function (): void {
    $entity = entityWithOwner();
    giveOpenRequests($entity, [openRequest('ACC-2026-000014'), openRequest('ADV-2026-000002', EntityRequestPath::Advanced, 2)]);
    signIn(systemAdmin());

    $this->deleteJson("/api/v1/entities/{$entity->id}", ['reason' => DELETION_REASON])
        ->assertConflict()
        ->assertJsonPath('code', 'entity.open_requests_confirmation_required')
        ->assertJsonPath('meta.open_requests_count', 2)
        ->assertJsonPath('meta.open_requests.1.number', 'ADV-2026-000002');

    expect($entity->refresh()->trashed())->toBeFalse();
});

it('refuses a stale acknowledgement of the open requests', function (array $acknowledged, bool $confirm): void {
    $entity = entityWithOwner();
    giveOpenRequests($entity, [openRequest('ACC-2026-000014'), openRequest('ADV-2026-000002', EntityRequestPath::Advanced, 2)]);
    signIn(systemAdmin());

    $this->deleteJson("/api/v1/entities/{$entity->id}", [
        'reason' => DELETION_REASON,
        'confirm_open_requests' => $confirm,
        'acknowledged_request_numbers' => $acknowledged,
    ])->assertConflict()->assertJsonPath('code', 'entity.open_requests_confirmation_required');

    expect($entity->refresh()->trashed())->toBeFalse();
})->with([
    'a request created meanwhile' => [['ACC-2026-000014'], true],
    'an unknown request' => [['ACC-2026-000014', 'ADV-2026-000099'], true],
    'confirmation flag missing' => [['ACC-2026-000014', 'ADV-2026-000002'], false],
]);

it('deletes an entity with open requests after an explicit confirmation and freezes them', function (): void {
    $entity = entityWithOwner();
    giveOpenRequests($entity, [openRequest('ACC-2026-000014'), openRequest('ADV-2026-000002', EntityRequestPath::Advanced, 2)]);
    signIn(systemAdmin());

    $this->deleteJson("/api/v1/entities/{$entity->id}", [
        'reason' => DELETION_REASON,
        'confirm_open_requests' => true,
        'acknowledged_request_numbers' => ['ADV-2026-000002', 'ACC-2026-000014'],
    ])->assertNoContent();

    expect(Audit::query()->where('event', AuditEvent::EntitySoftDeleted->value)->firstOrFail()->new_values['frozen_request_numbers'])
        ->toBe(['ACC-2026-000014', 'ADV-2026-000002'])
        ->and(app(EntityFreezeGuard::class)->isFrozen($entity->id))->toBeTrue();
    expect(fn () => app(EntityFreezeGuard::class)->assertEntityNotFrozen($entity->id))->toThrow(EntityRejected::class);
});

it('rejects every action on a deleted entity with 409 entity.deleted', function (string $method, string $uri, array $body): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->pendingVerification());
    $entity->delete();
    signIn(systemAdmin());

    $payload = $body === ['profile'] ? [...profilePayload($entity), 'reason' => 'تصحيح البيانات الأساسية للجهة.'] : $body;

    $this->json($method, "/api/v1/entities/{$entity->id}{$uri}", $payload)
        ->assertConflict()
        ->assertJsonPath('code', 'entity.deleted');
})->with([
    'deactivate' => ['POST', '/deactivate', ['reason' => 'سبب موثق للإجراء الإداري.']],
    'reactivate' => ['POST', '/reactivate', ['reason' => 'سبب موثق للإجراء الإداري.']],
    'resend verification' => ['POST', '/verification/resend', []],
    'edit on behalf' => ['PUT', '', ['profile']],
]);

it('rejects the approval of an e-mail change of a deleted entity', function (): void {
    $entity = entityWithOwner();
    $owner = ownerOf($entity);
    $change = EmailChangeRequest::query()->create([
        'user_id' => $owner->id, 'entity_id' => $entity->id, 'old_email' => $owner->email, 'new_email' => 'new@alamal.example.sa',
        'policy' => EmailChangePolicy::RequiresApproval, 'status' => EmailChangeStatus::AwaitingApproval,
    ]);
    $entity->delete();
    signIn(systemAdmin());

    $this->postJson("/api/v1/admin/entity-email-changes/{$change->id}/approve")->assertConflict()->assertJsonPath('code', 'entity.deleted');
    expect($owner->refresh()->email)->not->toBe('new@alamal.example.sa');
});

it('blocks the sign-in of a deleted entity with the generic credentials error', function (): void {
    $entity = entityWithOwner();
    $entity->delete();

    $this->postJson('/api/v1/auth/login', ['email' => ownerOf($entity)->email, 'mode' => 'password', 'password' => defaultPassword()])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'credentials.invalid');
});

it('shows a deleted entity only to holders of the delete permission', function (): void {
    $entity = entityWithOwner();
    $entity->delete();

    signIn(staffUser(RoleKey::RequestFollower));
    $this->getJson("/api/v1/entities/{$entity->id}")->assertNotFound();
});

it('shows a deleted entity marked as frozen to the system administrator', function (): void {
    $entity = entityWithOwner();
    $entity->delete();
    signIn(systemAdmin());

    $this->getJson("/api/v1/entities/{$entity->id}")->assertOk()->assertJsonPath('data.is_deleted', true)->assertJsonPath('data.is_frozen', true);
});

it('restores a deleted entity in its previous status and unfreezes its requests', function (): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->deactivated());
    giveOpenRequests($entity, [openRequest('ACC-2026-000014')]);
    $entity->delete();
    signIn(systemAdmin());

    $this->postJson("/api/v1/entities/{$entity->id}/restore", ['reason' => 'تراجعت الجهة عن طلب الإغلاق.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'deactivated')
        ->assertJsonPath('data.is_frozen', false);

    expect($entity->refresh()->trashed())->toBeFalse()
        ->and($entity->restored_at)->not->toBeNull()
        ->and(app(EntityFreezeGuard::class)->isFrozen($entity->id))->toBeFalse()
        ->and(Audit::query()->where('event', AuditEvent::EntityRestored->value)->firstOrFail()->new_values['unfrozen_request_numbers'])->toBe(['ACC-2026-000014']);
});

it('rejects restoring an entity that is not deleted and deleting one twice', function (): void {
    $entity = entityWithOwner();
    signIn(systemAdmin());

    $this->postJson("/api/v1/entities/{$entity->id}/restore", ['reason' => 'تراجعت الجهة عن طلب الإغلاق.'])->assertConflict()->assertJsonPath('code', 'state.transition_illegal');

    $entity->delete();
    $this->deleteJson("/api/v1/entities/{$entity->id}", ['reason' => DELETION_REASON])->assertConflict()->assertJsonPath('code', 'state.transition_illegal');
});

it('requires a reason to delete and to restore', function (string $method, string $uri): void {
    $entity = entityWithOwner();
    signIn(systemAdmin());

    $this->json($method, "/api/v1/entities/{$entity->id}{$uri}", [])->assertUnprocessable()->assertJsonValidationErrors(['reason']);
})->with([['DELETE', ''], ['POST', '/restore']]);

it('denies deletion, restore and the deletion impact without the delete permission', function (string $method, string $uri): void {
    $entity = entityWithOwner();
    signIn(staffUser(RoleKey::RequestFollower));

    $this->json($method, "/api/v1/entities/{$entity->id}{$uri}", ['reason' => DELETION_REASON])->assertForbidden();
})->with([['DELETE', ''], ['POST', '/restore'], ['GET', '/deletion-impact']]);

it('permanently deletes a soft-deleted entity for the Super Admin and keeps what the law requires', function (): void {
    $step = recordErasureSteps();
    $entity = entityWithOwner(static fn ($factory) => $factory->named('جمعية الأمل الخيرية', 'Al-Amal Charitable Association'));
    $owner = ownerOf($entity);
    $terms = publishTerms(TermsKind::Terms, 'erasure-1');
    $owner->termsAcceptances()->create(['terms_version_id' => $terms->id, 'ip_address' => '10.0.0.5']);
    AuthenticationEvent::query()->create(['user_id' => $owner->id, 'type' => 'login_success']);
    EntityProfileChange::query()->create(['entity_id' => $entity->id, 'field' => 'employees_count', 'old_value' => 10, 'new_value' => 12]);
    PrivacyRequest::query()->create(['entity_id' => $entity->id, 'kind' => 'access', 'details' => 'طلب نسخة من البيانات الشخصية.', 'created_by_user_id' => systemAdmin()->id]);
    OtpCode::query()->create(['challenge_id' => (string) Str::uuid(), 'user_id' => $owner->id, 'channel' => 'email', 'purpose' => 'login', 'code_hash' => 'x', 'destination_masked' => 'i***@example.sa', 'expires_at' => now(), 'last_sent_at' => now()]);
    $entity->delete();
    $reference = $entity->reference();
    signIn(superAdmin());
    $auditsBefore = Audit::query()->count();

    $response = $this->deleteJson("/api/v1/entities/{$entity->id}/permanent", [
        'reason' => 'تنفيذ طلب حذف نهائي معتمد من الجهة وفق نظام حماية البيانات الشخصية.',
        'confirmation' => strtolower($reference),
        'disclaimer_acknowledged' => true,
    ])->assertOk()
        ->assertJsonPath('data.reference', $reference)
        ->assertJsonPath('data.kept', ['payments_receipts_refunds', 'audit_log', 'anonymized_results', 'certificate_verification_records', 'consent_records', 'security_events']);

    $pseudonym = $response->json('data.pseudonym');
    $entity = Entity::withTrashed()->findOrFail($entity->id);
    $owner = User::withTrashed()->findOrFail($owner->id);

    expect($pseudonym)->toStartWith('ERASED-')
        ->and($entity->isErased())->toBeTrue()
        ->and($entity->name->ar)->toBe($pseudonym)
        ->and($entity->deletion_reason)->toBeNull()
        ->and($entity->region_id)->not->toBeNull()
        ->and($owner->trashed())->toBeTrue()
        ->and($owner->name)->toBe($pseudonym)
        ->and($owner->email)->toEndWith('@erased.invalid')
        ->and($owner->phone)->toBeNull()
        ->and($owner->password)->toBeNull()
        ->and($owner->status)->toBe(UserStatus::Suspended)
        ->and($owner->roles()->count())->toBe(0)
        ->and(OtpCode::query()->where('user_id', $owner->id)->count())->toBe(0)
        ->and(EntityProfileChange::query()->where('entity_id', $entity->id)->count())->toBe(0)
        ->and(PrivacyRequest::query()->where('entity_id', $entity->id)->count())->toBe(0)
        ->and(TermsAcceptance::query()->where('user_id', $owner->id)->count())->toBe(1)
        ->and(AuthenticationEvent::query()->where('user_id', $owner->id)->count())->toBe(1)
        ->and(Audit::query()->count())->toBe($auditsBefore + 1)
        ->and($step->erased)->toBe([['id' => $entity->id, 'name' => 'جمعية الأمل الخيرية']]);

    $audit = Audit::query()->where('event', AuditEvent::EntityPermanentlyDeleted->value)->firstOrFail();
    expect($audit->reason)->toBe('تنفيذ طلب حذف نهائي معتمد من الجهة وفق نظام حماية البيانات الشخصية.')
        ->and(json_encode($audit->old_values))->not->toContain('جمعية');
    Notification::assertSentTo(User::query()->superAdmins()->get(), SecurityAlertNotification::class);

    $this->getJson("/api/v1/entities/{$entity->id}")->assertNotFound();
});

it('refuses permanent deletion to every other role, including the system administrator', function (RoleKey $role): void {
    $entity = entityWithOwner();
    $entity->delete();
    signIn(staffUser($role));

    $this->deleteJson("/api/v1/entities/{$entity->id}/permanent", [
        'reason' => 'تنفيذ طلب حذف نهائي معتمد من الجهة وفق النظام.',
        'confirmation' => $entity->reference(),
        'disclaimer_acknowledged' => true,
    ])->assertForbidden();

    expect(Entity::withTrashed()->findOrFail($entity->id)->isErased())->toBeFalse();
})->with([RoleKey::SystemAdmin, RoleKey::RequestFollower, RoleKey::FinanceOfficer]);

it('permanently deletes only an entity that is already in Deleted entities', function (): void {
    $entity = entityWithOwner();
    signIn(superAdmin());

    $this->deleteJson("/api/v1/entities/{$entity->id}/permanent", [
        'reason' => 'تنفيذ طلب حذف نهائي معتمد من الجهة وفق النظام.',
        'confirmation' => $entity->reference(),
        'disclaimer_acknowledged' => true,
    ])->assertConflict()->assertJsonPath('code', 'entity.not_soft_deleted');
});

it('requires the typed reference, the disclaimer and a reason for permanent deletion', function (array $body, string $code, string $field): void {
    $entity = entityWithOwner();
    $entity->delete();
    signIn(superAdmin());

    $this->deleteJson("/api/v1/entities/{$entity->id}/permanent", [
        'reason' => 'تنفيذ طلب حذف نهائي معتمد من الجهة وفق النظام.',
        'confirmation' => $entity->reference(),
        'disclaimer_acknowledged' => true,
        ...$body,
    ])->assertUnprocessable()->assertJsonPath('code', $code)->assertJsonValidationErrors([$field]);

    expect(Entity::withTrashed()->findOrFail($entity->id)->isErased())->toBeFalse();
})->with([
    'wrong reference' => [['confirmation' => 'ENT-999999'], 'confirmation.mismatch', 'confirmation'],
    'disclaimer not acknowledged' => [['disclaimer_acknowledged' => false], 'disclaimer.required', 'disclaimer_acknowledged'],
    'reason too short' => [['reason' => 'حذف نهائي'], 'validation_failed', 'reason'],
    'confirmation missing' => [['confirmation' => ''], 'validation_failed', 'confirmation'],
]);
