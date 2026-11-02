<?php

declare(strict_types=1);

use App\Enums\Identity\InvitationStatus;
use App\Enums\Identity\RoleKey;
use App\Enums\Identity\UserStatus;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use App\Notifications\Identity\StaffInvitationNotification;
use App\Services\Identity\SessionKeys;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

const INVITEE_PASSWORD = 'Tuwaiq-Mountain-2026';

function inviteStaff(User $administrator, string $email = 'new.assessor@moashir.example.sa'): string
{
    test()->actingAs($administrator, 'web')
        ->withSession([SessionKeys::SIGNED_IN_AT => time(), SessionKeys::LAST_SEEN_AT => time()])
        ->postJson('/api/v1/users', ['name' => 'سلمان المطيري', 'email' => $email, 'roles' => ['assessor']])
        ->assertCreated();

    $invitee = User::query()->where('email', $email)->firstOrFail();
    $token = '';

    Notification::assertSentTo($invitee, StaffInvitationNotification::class, function (StaffInvitationNotification $notification) use ($invitee, &$token): bool {
        $token = basename((string) $notification->toMail($invitee)->actionUrl);

        return true;
    });

    app('auth')->guard('web')->logout();

    return $token;
}

beforeEach(function (): void {
    Notification::fake();
});

it('accepts an invitation, sets the password and requires TOTP enrolment', function (): void {
    $token = inviteStaff(systemAdmin());

    $this->postJson("/api/v1/auth/invitations/{$token}/accept", ['password' => INVITEE_PASSWORD, 'password_confirmation' => INVITEE_PASSWORD])
        ->assertOk()
        ->assertJsonPath('data.step', 'mfa_enrollment');

    $invitee = User::query()->where('email', 'new.assessor@moashir.example.sa')->firstOrFail();
    expect($invitee->status)->toBe(UserStatus::Active)
        ->and($invitee->password)->not->toBeNull()
        ->and($invitee->invitations()->first()?->status)->toBe(InvitationStatus::Accepted);
    $this->assertGuest('web');
});

it('cannot be used twice', function (): void {
    $token = inviteStaff(systemAdmin());
    $payload = ['password' => INVITEE_PASSWORD, 'password_confirmation' => INVITEE_PASSWORD];

    $this->postJson("/api/v1/auth/invitations/{$token}/accept", $payload)->assertOk();
    $this->postJson("/api/v1/auth/invitations/{$token}/accept", $payload)->assertNotFound()->assertJsonPath('code', 'invitation.invalid');
});

it('expires after 72 hours', function (): void {
    $token = inviteStaff(systemAdmin());

    $this->travel(73)->hours();

    $this->postJson("/api/v1/auth/invitations/{$token}/accept", ['password' => INVITEE_PASSWORD, 'password_confirmation' => INVITEE_PASSWORD])
        ->assertStatus(410)
        ->assertJsonPath('code', 'invitation.expired');
});

it('marks expired invitations and their users from the scheduler', function (): void {
    inviteStaff(systemAdmin());
    $this->travel(73)->hours();

    Artisan::call('moashir:expire-invitations');

    expect(User::query()->where('email', 'new.assessor@moashir.example.sa')->firstOrFail()->status)->toBe(UserStatus::Expired);
});

it('rejects an unknown token', function (): void {
    $this->postJson('/api/v1/auth/invitations/unknown-token/accept', ['password' => INVITEE_PASSWORD, 'password_confirmation' => INVITEE_PASSWORD])
        ->assertNotFound();
});

it('requires the current terms when a terms version is published', function (): void {
    $administrator = systemAdmin();
    $terms = TermsVersion::query()->create([
        'kind' => 'terms',
        'version' => '2026-10',
        'body' => ['ar' => 'نص الشروط والأحكام لمنصة مؤشر.', 'en' => 'The terms and conditions of the Moashir platform.'],
        'effective_from' => now()->toDateString(),
        'is_current' => true,
        'published_by_user_id' => $administrator->id,
    ]);
    $token = inviteStaff($administrator);

    $this->postJson("/api/v1/auth/invitations/{$token}/accept", ['password' => INVITEE_PASSWORD, 'password_confirmation' => INVITEE_PASSWORD])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('terms_version_id');

    $this->postJson("/api/v1/auth/invitations/{$token}/accept", ['password' => INVITEE_PASSWORD, 'password_confirmation' => INVITEE_PASSWORD, 'terms_version_id' => $terms->id])
        ->assertOk();

    expect(User::query()->where('email', 'new.assessor@moashir.example.sa')->firstOrFail()->termsAcceptances()->count())->toBe(1);
});

it('enforces the password policy on acceptance', function (): void {
    $token = inviteStaff(systemAdmin());

    $this->postJson("/api/v1/auth/invitations/{$token}/accept", ['password' => 'short', 'password_confirmation' => 'short'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');
});

it('rejects a password from the local breached list on acceptance without any network call', function (): void {
    Http::fake(static fn (): never => throw new RuntimeException('Outbound HTTP is forbidden.'));
    markPasswordBreached('Tuwaiq-Breached-2026');
    $token = inviteStaff(systemAdmin());

    $this->postJson("/api/v1/auth/invitations/{$token}/accept", ['password' => 'Tuwaiq-Breached-2026', 'password_confirmation' => 'Tuwaiq-Breached-2026'], ['Accept-Language' => 'en'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.password.0', 'This password has appeared in a known data breach. Please choose a different password.');
    Http::assertNothingSent();
});

it('resends an invitation and revokes the previous link', function (): void {
    $administrator = systemAdmin();
    $oldToken = inviteStaff($administrator);
    $invitee = User::query()->where('email', 'new.assessor@moashir.example.sa')->firstOrFail();
    signIn($administrator);

    $this->postJson("/api/v1/users/{$invitee->id}/invitation/resend")->assertAccepted();
    app('auth')->guard('web')->logout();

    $this->postJson("/api/v1/auth/invitations/{$oldToken}/accept", ['password' => INVITEE_PASSWORD, 'password_confirmation' => INVITEE_PASSWORD])->assertNotFound();
    Notification::assertSentToTimes($invitee, StaffInvitationNotification::class, 2);
});

it('refuses to resend an invitation to an active user', function (): void {
    $administrator = systemAdmin();
    $active = User::factory()->withRole(RoleKey::Assessor)->create();
    signIn($administrator);

    $this->postJson("/api/v1/users/{$active->id}/invitation/resend")
        ->assertConflict()
        ->assertJsonPath('code', 'state.transition_illegal')
        ->assertJsonPath('errors.state.0', 'active');
});
