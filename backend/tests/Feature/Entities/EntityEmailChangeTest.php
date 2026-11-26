<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\EmailChangePolicy;
use App\Enums\Identity\EmailChangeStatus;
use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Models\Audit\Audit;
use App\Models\Entities\Entity;
use App\Models\Identity\EmailChangeRequest;
use App\Models\Identity\Role;
use App\Models\Identity\User;
use App\Notifications\Entities\EntityNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;

const NEW_OFFICIAL_EMAIL = 'office@alamal.example.sa';

beforeEach(function (): void {
    Notification::fake();
});

/**
 * @return array{0: Entity, 1: User, 2: string}
 */
function requestEmailChange(): array
{
    $entity = entityWithOwner();
    $owner = ownerOf($entity);
    signIn($owner);

    $challengeId = test()->postJson('/api/v1/entities/me/email/change', ['new_email' => NEW_OFFICIAL_EMAIL])
        ->assertOk()
        ->assertJsonPath('data.otp.channel', 'email')
        ->json('data.otp.challenge_id');

    return [$entity, $owner, (string) $challengeId];
}

function confirmEmailChange(string $challengeId): TestResponse
{
    return test()->postJson('/api/v1/entities/me/email/confirm', ['challenge_id' => $challengeId, 'code' => otpCodeSentToAddress(NEW_OFFICIAL_EMAIL)]);
}

function mailedTo(string $address, NotificationCode $code): bool
{
    $sent = false;

    Notification::assertSentOnDemand(EntityNotification::class, static function (EntityNotification $notification, array $channels, AnonymousNotifiable $notifiable) use ($address, $code, &$sent): bool {
        $sent = $sent || (($notifiable->routes['mail'] ?? null) === $address && $notification->notice->code === $code);

        return true;
    });

    return $sent;
}

it('sends the verification code to the new address only', function (): void {
    [, $owner] = requestEmailChange();

    expect(otpCodeSentToAddress(NEW_OFFICIAL_EMAIL))->toMatch('/^\d{6}$/')
        ->and(EmailChangeRequest::query()->where('user_id', $owner->id)->value('status'))->toBe(EmailChangeStatus::AwaitingOtp);
});

it('waits for approval after the OTP by default and alerts the old address and the approvers', function (): void {
    $approver = systemAdmin();
    [, $owner, $challengeId] = requestEmailChange();
    $oldEmail = $owner->email;

    confirmEmailChange($challengeId)
        ->assertOk()
        ->assertJsonPath('data.status', 'awaiting_approval')
        ->assertJsonPath('data.policy', 'requires_approval')
        ->assertJsonPath('data.new_email', 'o***@alamal.example.sa');

    expect($owner->refresh()->email)->toBe($oldEmail)
        ->and(mailedTo($oldEmail, NotificationCode::EntityEmailChangeRequested))->toBeTrue()
        ->and(Audit::query()->where('event', AuditEvent::EntityEmailChangeRequested->value)->value('new_values'))->toMatchArray(['email' => 'o***@alamal.example.sa']);
    Notification::assertSentTo($approver, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::EntityEmailChangeRequested);
});

it('shows the pending change in the own profile', function (): void {
    [, , $challengeId] = requestEmailChange();
    confirmEmailChange($challengeId)->assertOk();

    $this->getJson('/api/v1/entities/me')->assertOk()->assertJsonPath('data.pending_email_change.status', 'awaiting_approval');
});

it('applies the change when an approver approves it and informs both addresses', function (): void {
    [$entity, $owner, $challengeId] = requestEmailChange();
    $oldEmail = $owner->email;
    confirmEmailChange($challengeId)->assertOk();
    $change = EmailChangeRequest::query()->where('user_id', $owner->id)->firstOrFail();
    switchTo(systemAdmin());

    $this->getJson('/api/v1/admin/entity-email-changes')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.new_email', NEW_OFFICIAL_EMAIL)
        ->assertJsonPath('data.0.entity.id', $entity->id);

    $this->postJson("/api/v1/admin/entity-email-changes/{$change->id}/approve")->assertOk()->assertJsonPath('data.status', 'applied');

    expect($owner->refresh()->email)->toBe(NEW_OFFICIAL_EMAIL)
        ->and(mailedTo($oldEmail, NotificationCode::EntityEmailChangeDecided))->toBeTrue()
        ->and(Audit::query()->where('event', AuditEvent::EntityEmailChangeApproved->value)->exists())->toBeTrue();
    Notification::assertSentTo($owner, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::EntityEmailChangeDecided);
});

it('lets the entity sign in with the new address once applied', function (): void {
    [, $owner, $challengeId] = requestEmailChange();
    confirmEmailChange($challengeId)->assertOk();
    $change = EmailChangeRequest::query()->where('user_id', $owner->id)->firstOrFail();
    switchTo(systemAdmin());
    $this->postJson("/api/v1/admin/entity-email-changes/{$change->id}/approve")->assertOk();
    auth()->guard('web')->logout();
    $this->flushSession();

    signInWith(NEW_OFFICIAL_EMAIL, defaultPassword())
        ->assertOk()
        ->assertJsonPath('data.step', 'authenticated');
});

it('rejects a change with a mandatory reason and keeps the current address', function (): void {
    [, $owner, $challengeId] = requestEmailChange();
    $oldEmail = $owner->email;
    confirmEmailChange($challengeId)->assertOk();
    $change = EmailChangeRequest::query()->where('user_id', $owner->id)->firstOrFail();
    switchTo(systemAdmin());

    $this->postJson("/api/v1/admin/entity-email-changes/{$change->id}/reject", [])->assertUnprocessable()->assertJsonValidationErrors(['reason']);
    $this->postJson("/api/v1/admin/entity-email-changes/{$change->id}/reject", ['reason' => 'لم يرد خطاب رسمي من الجهة يؤكد التغيير.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.decision_reason', 'لم يرد خطاب رسمي من الجهة يؤكد التغيير.');

    expect($owner->refresh()->email)->toBe($oldEmail)
        ->and(mailedTo(NEW_OFFICIAL_EMAIL, NotificationCode::EntityEmailChangeDecided))->toBeTrue();
});

it('refuses to decide a request twice', function (string $verb): void {
    [, $owner, $challengeId] = requestEmailChange();
    confirmEmailChange($challengeId)->assertOk();
    $change = EmailChangeRequest::query()->where('user_id', $owner->id)->firstOrFail();
    switchTo(systemAdmin());
    $this->postJson("/api/v1/admin/entity-email-changes/{$change->id}/approve")->assertOk();

    $this->postJson("/api/v1/admin/entity-email-changes/{$change->id}/{$verb}", ['reason' => 'قرار مكرر على الطلب نفسه.'])
        ->assertConflict()
        ->assertJsonPath('code', 'state.transition_illegal');
})->with(['approve', 'reject']);

it('applies the change immediately and only informs the administration under the immediate policy', function (): void {
    config(['moashir.entities.email_change_policy' => EmailChangePolicy::ImmediateNotify->value]);
    $admin = systemAdmin();
    [, $owner, $challengeId] = requestEmailChange();
    $oldEmail = $owner->email;

    confirmEmailChange($challengeId)->assertOk()->assertJsonPath('data.status', 'applied')->assertJsonPath('data.policy', 'immediate_notify');

    expect($owner->refresh()->email)->toBe(NEW_OFFICIAL_EMAIL)
        ->and(mailedTo($oldEmail, NotificationCode::EntityEmailChangeDecided))->toBeTrue()
        ->and(Audit::query()->where('event', AuditEvent::EntityEmailChangeApplied->value)->value('old_values'))->toMatchArray(['email' => mb_substr($oldEmail, 0, 1).'***@'.explode('@', $oldEmail)[1]]);
    Notification::assertSentTo($admin, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::EntityEmailChangeRequested);
});

it('refuses a new request while one awaits approval and replaces one still awaiting its code', function (): void {
    [, $owner, $challengeId] = requestEmailChange();

    $this->postJson('/api/v1/entities/me/email/change', ['new_email' => 'second@alamal.example.sa'])->assertOk();
    expect(EmailChangeRequest::query()->where('user_id', $owner->id)->where('status', EmailChangeStatus::Cancelled)->count())->toBe(1);

    $this->postJson('/api/v1/entities/me/email/confirm', ['challenge_id' => $challengeId, 'code' => '000000'])->assertUnprocessable();

    $latest = EmailChangeRequest::query()->where('user_id', $owner->id)->where('status', EmailChangeStatus::AwaitingOtp)->firstOrFail();
    $latest->forceFill(['status' => EmailChangeStatus::AwaitingApproval])->save();

    $this->postJson('/api/v1/entities/me/email/change', ['new_email' => 'third@alamal.example.sa'])->assertConflict()->assertJsonPath('code', 'email_change.pending_approval');
});

it('rejects a wrong code without changing anything', function (): void {
    [, $owner, $challengeId] = requestEmailChange();
    $wrong = otpCodeSentToAddress(NEW_OFFICIAL_EMAIL) === '000000' ? '111111' : '000000';

    $this->postJson('/api/v1/entities/me/email/confirm', ['challenge_id' => $challengeId, 'code' => $wrong])->assertUnprocessable()->assertJsonPath('code', 'otp.invalid');

    expect(EmailChangeRequest::query()->where('user_id', $owner->id)->value('status'))->toBe(EmailChangeStatus::AwaitingOtp);
});

it('validates the new address', function (string $address): void {
    $entity = entityWithOwner();
    $owner = ownerOf($entity);
    $taken = User::factory()->create(['email' => 'taken@alamal.example.sa']);
    signIn($owner);

    $value = match ($address) {
        'current' => $owner->email,
        'taken' => $taken->email,
        default => $address,
    };

    $this->postJson('/api/v1/entities/me/email/change', ['new_email' => $value])->assertUnprocessable()->assertJsonValidationErrors(['new_email']);
})->with(['current', 'taken', 'not-an-email', '']);

it('rejects the approval when the address was taken in the meantime', function (): void {
    [, $owner, $challengeId] = requestEmailChange();
    confirmEmailChange($challengeId)->assertOk();
    User::factory()->create(['email' => NEW_OFFICIAL_EMAIL]);
    $change = EmailChangeRequest::query()->where('user_id', $owner->id)->firstOrFail();
    switchTo(systemAdmin());

    $this->postJson("/api/v1/admin/entity-email-changes/{$change->id}/approve")->assertConflict()->assertJsonPath('code', 'email_change.address_taken');
});

it('denies the approval queue to staff without the independent permission', function (RoleKey $role): void {
    signIn(staffUser($role));

    $this->getJson('/api/v1/admin/entity-email-changes')->assertForbidden();
})->with([RoleKey::RequestFollower, RoleKey::FinanceOfficer, RoleKey::ContentOfficer]);

it('lets any role granted the independent permission approve changes', function (): void {
    Role::findByName(RoleKey::RequestFollower->value)->givePermissionTo(PermissionKey::EntitiesEmailChangeApprove->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    signIn(staffUser(RoleKey::RequestFollower));

    $this->getJson('/api/v1/admin/entity-email-changes')->assertOk();
});

it('denies the own e-mail change to staff', function (): void {
    signIn(systemAdmin());

    $this->postJson('/api/v1/entities/me/email/change', ['new_email' => NEW_OFFICIAL_EMAIL])->assertForbidden();
});

it('rate limits e-mail change requests', function (): void {
    $entity = entityWithOwner();
    signIn(ownerOf($entity));

    foreach (range(1, 3) as $attempt) {
        $this->postJson('/api/v1/entities/me/email/change', ['new_email' => "office{$attempt}@alamal.example.sa"])->assertOk();
    }

    $this->postJson('/api/v1/entities/me/email/change', ['new_email' => 'office4@alamal.example.sa'])->assertTooManyRequests();
});

it('lets the entity cancel its pending change, audits it and keeps the current address', function (): void {
    [$entity, $owner, $challengeId] = requestEmailChange();
    confirmEmailChange($challengeId)->assertOk();
    $oldEmail = $owner->email;

    switchTo($owner)->postJson('/api/v1/entities/me/email/cancel')
        ->assertOk()
        ->assertJsonPath('meta.changed', true)
        ->assertJsonPath('data.pending_email_change', null)
        ->assertJsonPath('data.official_email', $oldEmail);

    $audit = Audit::query()->where('event', AuditEvent::EntityEmailChangeCancelled->value)->sole();

    expect(EmailChangeRequest::query()->where('user_id', $owner->id)->value('status'))->toBe(EmailChangeStatus::Cancelled)
        ->and($owner->refresh()->email)->toBe($oldEmail)
        ->and($audit->auditable_id)->toBe($entity->id)
        ->and($audit->user_id)->toBe($owner->id)
        ->and($audit->old_values)->toBe(['status' => 'awaiting_approval'])
        ->and($audit->new_values)->toBe(['status' => 'cancelled'])
        ->and(json_encode([$audit->old_values, $audit->new_values]))->not->toContain('alamal.example.sa');

    switchTo($owner)->postJson('/api/v1/entities/me/email/change', ['new_email' => 'board@alamal.example.sa'])->assertOk();
});

it('cancels idempotently and audits only a real cancellation', function (): void {
    [, $owner, $challengeId] = requestEmailChange();
    confirmEmailChange($challengeId)->assertOk();

    switchTo($owner)->postJson('/api/v1/entities/me/email/cancel')->assertOk()->assertJsonPath('meta.changed', true);
    switchTo($owner)->postJson('/api/v1/entities/me/email/cancel')->assertOk()->assertJsonPath('meta.changed', false)->assertJsonPath('data.pending_email_change', null);

    expect(Audit::query()->where('event', AuditEvent::EntityEmailChangeCancelled->value)->count())->toBe(1);
});

it('answers a cancellation with nothing pending without writing anything', function (): void {
    $entity = entityWithOwner();

    switchTo(ownerOf($entity))->postJson('/api/v1/entities/me/email/cancel')->assertOk()->assertJsonPath('meta.changed', false);

    expect(Audit::query()->where('event', AuditEvent::EntityEmailChangeCancelled->value)->count())->toBe(0);
});

it('also cancels a change still awaiting its code, which can then no longer be confirmed', function (): void {
    [, $owner, $challengeId] = requestEmailChange();

    switchTo($owner)->postJson('/api/v1/entities/me/email/cancel')->assertOk()->assertJsonPath('meta.changed', true);

    expect(EmailChangeRequest::query()->where('user_id', $owner->id)->value('status'))->toBe(EmailChangeStatus::Cancelled)
        ->and(Audit::query()->where('event', AuditEvent::EntityEmailChangeCancelled->value)->sole()->old_values)->toBe(['status' => 'awaiting_otp']);
    switchTo($owner);
    confirmEmailChange($challengeId)->assertStatus(422);
    expect($owner->refresh()->email)->not->toBe(NEW_OFFICIAL_EMAIL);
});

it('keeps a cancelled change out of the approvers hands', function (): void {
    [, $owner, $challengeId] = requestEmailChange();
    confirmEmailChange($challengeId)->assertOk();
    $change = EmailChangeRequest::query()->where('user_id', $owner->id)->firstOrFail();
    switchTo($owner)->postJson('/api/v1/entities/me/email/cancel')->assertOk();

    switchTo(systemAdmin())->postJson("/api/v1/admin/entity-email-changes/{$change->id}/approve")->assertConflict()->assertJsonPath('code', 'state.transition_illegal');

    expect($owner->refresh()->email)->not->toBe(NEW_OFFICIAL_EMAIL);
});

it('leaves an approved change in force when the entity cancels afterwards', function (): void {
    [, $owner, $challengeId] = requestEmailChange();
    confirmEmailChange($challengeId)->assertOk();
    $change = EmailChangeRequest::query()->where('user_id', $owner->id)->firstOrFail();
    switchTo(systemAdmin())->postJson("/api/v1/admin/entity-email-changes/{$change->id}/approve")->assertOk();

    switchTo($owner->refresh())->postJson('/api/v1/entities/me/email/cancel')->assertOk()
        ->assertJsonPath('meta.changed', false)
        ->assertJsonPath('data.official_email', NEW_OFFICIAL_EMAIL);
});

it('requires a session to cancel a change', function (): void {
    $this->postJson('/api/v1/entities/me/email/cancel')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
});
