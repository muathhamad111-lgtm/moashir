<?php

declare(strict_types=1);

use App\Enums\Identity\InvitationStatus;
use App\Enums\Identity\RoleKey;
use App\Enums\Identity\UserStatus;
use App\Models\Audit\Audit;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use App\Notifications\Identity\StaffInvitationNotification;
use App\Services\Identity\AuthSettings;
use App\Services\Identity\SessionKeys;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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

it('never stores the single-use invitation token in an audit row', function (): void {
    $token = inviteStaff(systemAdmin());

    $this->postJson("/api/v1/auth/invitations/{$token}/accept", ['password' => INVITEE_PASSWORD, 'password_confirmation' => INVITEE_PASSWORD])->assertOk();

    $urls = Audit::query()->pluck('url')->filter()->implode(' ');
    expect($urls)->toContain('/api/v1/auth/invitations/[redacted]/accept')
        ->and($urls)->not->toContain($token)
        ->and((string) json_encode(Audit::query()->get()->toArray()))->not->toContain($token);
});

it('cannot be used twice', function (): void {
    $token = inviteStaff(systemAdmin());
    $payload = ['password' => INVITEE_PASSWORD, 'password_confirmation' => INVITEE_PASSWORD];

    $this->postJson("/api/v1/auth/invitations/{$token}/accept", $payload)->assertOk();
    $this->postJson("/api/v1/auth/invitations/{$token}/accept", $payload)->assertNotFound()->assertJsonPath('code', 'invitation.invalid');
});

it('expires after 60 minutes', function (): void {
    $token = inviteStaff(systemAdmin());

    $this->travel(61)->minutes();

    $this->postJson("/api/v1/auth/invitations/{$token}/accept", ['password' => INVITEE_PASSWORD, 'password_confirmation' => INVITEE_PASSWORD])
        ->assertStatus(410)
        ->assertJsonPath('code', 'invitation.expired');
});

it('marks expired invitations and their users from the scheduler', function (): void {
    inviteStaff(systemAdmin());
    $this->travel(61)->minutes();

    Artisan::call('moashir:expire-invitations');

    expect(User::query()->where('email', 'new.assessor@moashir.example.sa')->firstOrFail()->status)->toBe(UserStatus::Expired);
});

it('rejects an unknown token', function (): void {
    $this->postJson('/api/v1/auth/invitations/unknown-token/accept', ['password' => INVITEE_PASSWORD, 'password_confirmation' => INVITEE_PASSWORD])
        ->assertNotFound();
});

it('requires the current terms when a terms version is published', function (): void {
    $administrator = systemAdmin();
    $terms = TermsVersion::query()->forceCreate([
        'kind' => 'terms',
        'version' => '2026-10',
        'body' => ['ar' => 'نص الشروط والأحكام لمنصة مؤشر.', 'en' => 'The terms and conditions of the Moashir platform.'],
        'effective_from' => now()->toDateString(),
        'is_current' => true,
        'published_at' => now(),
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

it('tells the invitation page that a fresh link is usable without returning personal data', function (): void {
    $token = inviteStaff(systemAdmin());

    $this->getJson("/api/v1/auth/invitations/{$token}")
        ->assertOk()
        ->assertExactJson(['data' => ['usable' => true, 'national_id_required' => false]]);
});

it('answers a used, revoked or unknown link with the same invalid response', function (string $case): void {
    $administrator = systemAdmin();
    $token = inviteStaff($administrator);
    $invitee = User::query()->where('email', 'new.assessor@moashir.example.sa')->firstOrFail();

    $token = match ($case) {
        'used' => tap($token, fn () => $this->postJson("/api/v1/auth/invitations/{$token}/accept", ['password' => INVITEE_PASSWORD, 'password_confirmation' => INVITEE_PASSWORD])->assertOk()),
        'revoked' => tap($token, static fn () => $invitee->invitations()->update(['status' => InvitationStatus::Revoked])),
        default => str_repeat('Qx', 32),
    };
    app('auth')->guard('web')->logout();

    $this->getJson("/api/v1/auth/invitations/{$token}")
        ->assertNotFound()
        ->assertJsonPath('code', 'invitation.invalid')
        ->assertJsonMissingPath('data.usable');
})->with(['used', 'revoked', 'unknown']);

it('answers an expired link with the expired code', function (): void {
    $token = inviteStaff(systemAdmin());
    $this->travel(61)->minutes();

    $this->getJson("/api/v1/auth/invitations/{$token}")
        ->assertStatus(410)
        ->assertJsonPath('code', 'invitation.expired');
});

it('rejects the old link on the check endpoint once the invitation is resent', function (): void {
    $administrator = systemAdmin();
    $oldToken = inviteStaff($administrator);
    $invitee = User::query()->where('email', 'new.assessor@moashir.example.sa')->firstOrFail();
    signIn($administrator);

    $this->postJson("/api/v1/users/{$invitee->id}/invitation/resend")->assertAccepted();
    app('auth')->guard('web')->logout();

    $this->getJson("/api/v1/auth/invitations/{$oldToken}")->assertNotFound()->assertJsonPath('code', 'invitation.invalid');
    expect($invitee->invitations()->where('status', InvitationStatus::Pending)->count())->toBe(1);
});

it('throttles the invitation check per address', function (): void {
    foreach (range(1, 10) as $attempt) {
        $this->getJson('/api/v1/auth/invitations/'.str_repeat('Qx', 32))->assertNotFound();
    }

    $this->getJson('/api/v1/auth/invitations/'.str_repeat('Qx', 32))->assertTooManyRequests();
});

it('issues invitations valid for at most 60 minutes whatever the configuration says', function (int $configured, int $expected): void {
    config(['moashir.auth.invitation_ttl_minutes' => $configured]);
    $this->freezeSecond();

    inviteStaff(systemAdmin());

    $invitation = User::query()->where('email', 'new.assessor@moashir.example.sa')->firstOrFail()->invitations()->firstOrFail();
    expect(app(AuthSettings::class)->invitationTtlMinutes())->toBe($expected)
        ->and((int) now()->diffInMinutes($invitation->expires_at))->toBe($expected);
})->with([
    'default' => [60, 60],
    'shorter' => [30, 30],
    'raised in the server env' => [4320, 60],
    'zero' => [0, 1],
]);

it('locks the invitation row inside the acceptance transaction', function (): void {
    $token = inviteStaff(systemAdmin());
    $locked = false;
    DB::listen(static function (QueryExecuted $query) use (&$locked): void {
        $locked = $locked || (str_contains($query->sql, 'user_invitations') && str_contains($query->sql, 'for update'));
    });

    $this->postJson("/api/v1/auth/invitations/{$token}/accept", ['password' => INVITEE_PASSWORD, 'password_confirmation' => INVITEE_PASSWORD])->assertOk();

    expect($locked)->toBeTrue();
});

it('rejects a second acceptance that was racing the first one', function (): void {
    $token = inviteStaff(systemAdmin());
    $payload = ['password' => INVITEE_PASSWORD, 'password_confirmation' => INVITEE_PASSWORD];

    $this->postJson("/api/v1/auth/invitations/{$token}/accept", $payload)->assertOk();
    app('auth')->guard('web')->logout();

    $this->postJson("/api/v1/auth/invitations/{$token}/accept", $payload)->assertNotFound()->assertJsonPath('code', 'invitation.invalid');
    expect(User::query()->where('email', 'new.assessor@moashir.example.sa')->firstOrFail()->invitations()->where('status', InvitationStatus::Accepted)->count())->toBe(1);
});

it('asks an invitee for the national ID only when their role is required, and stores it', function (): void {
    requireNationalIdFor(RoleKey::Assessor);
    $token = inviteStaff(systemAdmin());

    $this->getJson("/api/v1/auth/invitations/{$token}")->assertOk()->assertJsonPath('data.national_id_required', true);
    $this->postJson("/api/v1/auth/invitations/{$token}/accept", ['password' => INVITEE_PASSWORD, 'password_confirmation' => INVITEE_PASSWORD])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'identity.national_id_required');

    $this->postJson("/api/v1/auth/invitations/{$token}/accept", [
        'password' => INVITEE_PASSWORD,
        'password_confirmation' => INVITEE_PASSWORD,
        'national_id' => fictionalNationalId(808),
    ])->assertOk();

    expect(User::query()->where('email', 'new.assessor@moashir.example.sa')->sole()->national_id)->toBe(fictionalNationalId(808));
});

it('ignores a national ID sent by an invitee whose role is not required', function (): void {
    $token = inviteStaff(systemAdmin());

    $this->postJson("/api/v1/auth/invitations/{$token}/accept", [
        'password' => INVITEE_PASSWORD,
        'password_confirmation' => INVITEE_PASSWORD,
        'national_id' => fictionalNationalId(909),
    ])->assertOk();

    expect(User::query()->where('email', 'new.assessor@moashir.example.sa')->sole()->national_id)->toBeNull();
});
