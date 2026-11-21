<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Entities\EntityStatus;
use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Models\Audit\Audit;
use App\Models\Identity\Role;
use App\Models\Identity\UserSession;
use App\Notifications\Entities\EntityNotification;
use App\Notifications\Identity\OtpCodeNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

const REJECTION_REASON = 'رقم الترخيص المرفق لا يطابق سجل الجهة لدى المركز الوطني.';

beforeEach(function (): void {
    Notification::fake();
});

function isNoticeOf(NotificationCode $code): Closure
{
    return static fn (EntityNotification $notification): bool => $notification->notice->code === $code;
}

it('approves an entity awaiting review, notifies its owner and audits the decision', function (): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->pendingReview()->incompleteProfile());
    $actor = systemAdmin();
    signIn($actor);

    $this->postJson("/api/v1/entities/{$entity->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.id', $entity->id)
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.approved_at', $entity->refresh()->approved_at?->toIso8601String())
        ->assertJsonPath('data.rejected_at', null)
        ->assertJsonPath('data.rejection_reason', null);

    $audit = Audit::query()->where('event', AuditEvent::EntityApproved->value)->sole();

    expect($entity->status)->toBe(EntityStatus::Active)
        ->and($entity->approved_at)->not->toBeNull()
        ->and($audit->old_values)->toBe(['status' => 'pending_review'])
        ->and($audit->new_values)->toBe(['status' => 'active'])
        ->and($audit->user_id)->toBe($actor->id);
    Notification::assertSentTo(ownerOf($entity), EntityNotification::class, isNoticeOf(NotificationCode::EntityRegistrationApproved));
    Notification::assertSentTo(ownerOf($entity), EntityNotification::class, isNoticeOf(NotificationCode::EntityProfileIncomplete));
});

it('approves a rejected entity later, clears the rejection and records the optional reason', function (): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->rejected());
    signIn(systemAdmin());

    $this->postJson("/api/v1/entities/{$entity->id}/approve", ['reason' => 'قدّمت الجهة ترخيصًا محدّثًا مطابقًا للسجل.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.rejected_at', null)
        ->assertJsonPath('data.rejection_reason', null);

    $audit = Audit::query()->where('event', AuditEvent::EntityApproved->value)->sole();

    expect($entity->refresh()->status)->toBe(EntityStatus::Active)
        ->and($entity->rejected_at)->toBeNull()
        ->and($audit->old_values)->toBe(['status' => 'rejected'])
        ->and($audit->reason)->toBe('قدّمت الجهة ترخيصًا محدّثًا مطابقًا للسجل.');
    Notification::assertSentTo(ownerOf($entity), EntityNotification::class, isNoticeOf(NotificationCode::EntityRegistrationApproved));
    Notification::assertNotSentTo(ownerOf($entity), EntityNotification::class, isNoticeOf(NotificationCode::EntityProfileIncomplete));
});

it('lets the approved entity sign in', function (): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->pendingReview());
    signIn(systemAdmin());
    $this->postJson("/api/v1/entities/{$entity->id}/approve")->assertOk();
    $this->flushSession();
    app('auth')->forgetGuards();

    signInWith(ownerOf($entity)->email, defaultPassword())->assertOk()->assertJsonPath('data.step', 'authenticated');
});

it('approves an entity whose e-mail is not confirmed yet without opening a session before the owner confirms it', function (): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->pendingVerification());
    $owner = ownerOf($entity);
    signIn(systemAdmin());

    $this->postJson("/api/v1/entities/{$entity->id}/approve")->assertOk()->assertJsonPath('data.status', 'active');

    expect(Audit::query()->where('event', AuditEvent::EntityApproved->value)->sole()->old_values)->toBe(['status' => 'pending_verification']);
    Notification::assertSentTo($owner, EntityNotification::class, isNoticeOf(NotificationCode::EntityRegistrationApproved));
    $this->flushSession();
    app('auth')->forgetGuards();

    $challengeId = signInWith($owner->email, defaultPassword())
        ->assertOk()
        ->assertJsonPath('data.step', 'otp_required')
        ->json('data.otp.challenge_id');
    $this->assertGuest('web');

    $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => lastOtpCodeSentTo($owner)])
        ->assertOk()
        ->assertJsonPath('data.step', 'authenticated');

    $this->assertAuthenticatedAs($owner, 'web');
    expect($owner->refresh()->email_verified_at)->not->toBeNull()
        ->and($entity->refresh()->status)->toBe(EntityStatus::Active)
        ->and($entity->verified_at)->not->toBeNull();
});

it('rejects an entity with a reason, ends its sessions, e-mails the reason to the owner and audits the decision', function (string $state, string $before): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->{$state}());
    $owner = ownerOf($entity);
    UserSession::query()->forceCreate(['id' => 'entity-session', 'user_id' => $owner->id, 'payload' => '', 'last_activity' => now()->getTimestamp()]);
    $actor = systemAdmin();
    signIn($actor);

    $this->postJson("/api/v1/entities/{$entity->id}/reject", ['reason' => REJECTION_REASON])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.rejection_reason', REJECTION_REASON)
        ->assertJsonPath('data.rejected_at', $entity->refresh()->rejected_at?->toIso8601String());

    $audit = Audit::query()->where('event', AuditEvent::EntityRejected->value)->sole();

    expect($entity->status)->toBe(EntityStatus::Rejected)
        ->and($entity->rejection_reason)->toBe(REJECTION_REASON)
        ->and(UserSession::query()->where('user_id', $owner->id)->count())->toBe(0)
        ->and($audit->old_values)->toBe(['status' => $before])
        ->and($audit->new_values)->toBe(['status' => 'rejected'])
        ->and($audit->reason)->toBe(REJECTION_REASON)
        ->and($audit->user_id)->toBe($actor->id);
    Notification::assertSentTo($owner, EntityNotification::class, static fn (EntityNotification $notification, array $channels): bool => $notification->notice->code === NotificationCode::EntityRegistrationRejected
        && $channels === ['mail']
        && str_contains($notification->notice->body->ar, REJECTION_REASON));
})->with([
    'unverified' => ['pendingVerification', 'pending_verification'],
    'awaiting review' => ['pendingReview', 'pending_review'],
]);

it('refuses illegal review transitions with 409', function (string $state, string $verb): void {
    $entity = entityWithOwner(static fn ($factory) => $state === 'active' ? $factory : $factory->{$state}());
    signIn(systemAdmin());

    $this->postJson("/api/v1/entities/{$entity->id}/{$verb}", ['reason' => REJECTION_REASON])
        ->assertConflict()
        ->assertJsonPath('code', 'state.transition_illegal')
        ->assertJsonPath('errors.attempted.0', $verb);

    expect(Audit::query()->whereIn('event', [AuditEvent::EntityApproved->value, AuditEvent::EntityRejected->value])->exists())->toBeFalse();
})->with([
    'approve an active entity' => ['active', 'approve'],
    'approve a deactivated entity' => ['deactivated', 'approve'],
    'reject an active entity' => ['active', 'reject'],
    'reject a rejected entity' => ['rejected', 'reject'],
    'reject a deactivated entity' => ['deactivated', 'reject'],
]);

it('requires a reason of 10 to 1000 characters to reject', function (array $body): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->pendingReview());
    signIn(systemAdmin());

    $this->postJson("/api/v1/entities/{$entity->id}/reject", $body)->assertUnprocessable()->assertJsonValidationErrors(['reason']);

    expect($entity->refresh()->isPendingReview())->toBeTrue();
})->with([
    'missing' => [[]],
    'too short' => [['reason' => 'قصير']],
    'too long' => [['reason' => str_repeat('س', 1001)]],
]);

it('validates the optional approval reason', function (): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->pendingReview());
    signIn(systemAdmin());

    $this->postJson("/api/v1/entities/{$entity->id}/approve", ['reason' => 'قصير'])->assertUnprocessable()->assertJsonValidationErrors(['reason']);
});

it('requires a reason when the Super Admin approves', function (): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->pendingReview());
    signIn(superAdmin());

    $this->postJson("/api/v1/entities/{$entity->id}/approve")->assertUnprocessable()->assertJsonPath('code', 'reason_required');

    expect($entity->refresh()->isPendingReview())->toBeTrue();
});

it('denies the review to staff without entities.manage', function (string $verb): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->pendingReview());
    signIn(staffUser(RoleKey::RequestFollower));

    $this->postJson("/api/v1/entities/{$entity->id}/{$verb}", ['reason' => REJECTION_REASON])->assertForbidden();

    expect($entity->refresh()->isPendingReview())->toBeTrue();
})->with(['approve', 'reject']);

it('lets any role granted entities.manage review registrations', function (): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->pendingReview());
    Role::findByName(RoleKey::RequestFollower->value)->givePermissionTo(PermissionKey::EntitiesManage->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    signIn(staffUser(RoleKey::RequestFollower));

    $this->postJson("/api/v1/entities/{$entity->id}/approve")->assertOk();
});

it('denies the review to entity accounts and asks guests to sign in', function (string $verb): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->pendingReview());

    $this->postJson("/api/v1/entities/{$entity->id}/{$verb}", ['reason' => REJECTION_REASON])->assertUnauthorized();
    signIn(entityUser())->postJson("/api/v1/entities/{$entity->id}/{$verb}", ['reason' => REJECTION_REASON])->assertForbidden();
})->with(['approve', 'reject']);

it('refuses to review a deleted entity', function (string $verb): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->pendingReview());
    $entity->delete();
    signIn(systemAdmin());

    $this->postJson("/api/v1/entities/{$entity->id}/{$verb}", ['reason' => REJECTION_REASON])
        ->assertConflict()
        ->assertJsonPath('code', 'entity.deleted');
})->with(['approve', 'reject']);

it('answers the sign-in of an entity awaiting review or rejected with a distinct error and opens no session', function (string $state, string $code): void {
    allowSignInCodeChannels('email');
    $owner = ownerOf(entityWithOwner(static fn ($factory) => $factory->{$state}()));

    signInWith($owner->email, defaultPassword())->assertForbidden()->assertJsonPath('code', $code);
    $this->assertGuest('web');

    $challengeId = requestSignInCode($owner->email)->assertOk()->json('data.otp.challenge_id');
    $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => lastOtpCodeSentTo($owner)])
        ->assertForbidden()
        ->assertJsonPath('code', $code);

    $this->assertGuest('web');
    $this->getJson('/api/v1/auth/session')->assertUnauthorized();
})->with([
    'pending review' => ['pendingReview', 'auth.entity_pending_review'],
    'rejected' => ['rejected', 'auth.entity_rejected'],
]);

it('words the sign-in refusals in Arabic and English', function (): void {
    $owner = ownerOf(entityWithOwner(static fn ($factory) => $factory->pendingReview()));
    $rejected = ownerOf(entityWithOwner(static fn ($factory) => $factory->rejected()));

    signInWith($owner->email, defaultPassword())->assertJsonPath('message', 'طلب تسجيل جهتك قيد المراجعة لدى فريق منصة مؤشر، وسيصلك بريد عند اعتماده.');
    signInWith($rejected->email, defaultPassword())->assertJsonPath('message', 'رُفض طلب تسجيل جهتك. تواصل مع فريق منصة مؤشر للاستفسار.');
    $this->postJson('/api/v1/auth/login', ['identifier' => $rejected->email, 'password' => defaultPassword()], ['Accept-Language' => 'en'])
        ->assertJsonPath('message', 'Your entity registration was rejected. Contact the Moashir platform team for details.');
});

it('tells a rejected registration it was rejected when its pending e-mail code is used', function (): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->pendingVerification());
    $owner = ownerOf($entity);
    $challengeId = requestSignInCode($owner->email)->json('data.otp.challenge_id');
    $code = lastOtpCodeSentTo($owner);
    signIn(systemAdmin())->postJson("/api/v1/entities/{$entity->id}/reject", ['reason' => REJECTION_REASON])->assertOk();
    $this->flushSession();
    app('auth')->forgetGuards();

    $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challengeId, 'code' => $code])->assertForbidden()->assertJsonPath('code', 'auth.entity_rejected');

    expect($entity->refresh()->isRejected())->toBeTrue();
    Notification::assertSentTo($owner, OtpCodeNotification::class);
});

it('counts entities awaiting review and rejected ones and filters the list by them', function (): void {
    entityWithOwner(static fn ($factory) => $factory->pendingReview()->named('جمعية البر الأهلية'));
    entityWithOwner(static fn ($factory) => $factory->rejected());
    entityWithOwner();
    signIn(systemAdmin());

    $this->getJson('/api/v1/entities?filter[status]=pending_review')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name.ar', 'جمعية البر الأهلية')
        ->assertJsonPath('meta.stats.pending_review', 1)
        ->assertJsonPath('meta.stats.rejected', 1)
        ->assertJsonPath('meta.stats.active', 1);
    $this->getJson('/api/v1/entities?filter[status]=unknown')->assertUnprocessable();
});
