<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Models\Audit\Audit;
use App\Models\Identity\User;
use App\Notifications\Identity\PasswordResetLinkNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

function resetLinkUrl(User $user): string
{
    return "/api/v1/users/{$user->id}/password/reset-link";
}

it('e-mails an active staff user a password reset link and audits it', function (): void {
    $administrator = systemAdmin();
    $assessor = User::factory()->withRole(RoleKey::Assessor)->create();
    signIn($administrator);

    $this->postJson(resetLinkUrl($assessor))->assertAccepted();

    Notification::assertSentTo($assessor, PasswordResetLinkNotification::class);
    $audit = Audit::query()->where('event', AuditEvent::UserPasswordResetLinkSent->value)->sole();
    expect($audit->auditable_id)->toBe($assessor->id)
        ->and($audit->user_id)->toBe($administrator->id);
});

it('refuses a second link while the previous one is still fresh', function (): void {
    $administrator = systemAdmin();
    $assessor = User::factory()->withRole(RoleKey::Assessor)->create();
    signIn($administrator);

    $this->postJson(resetLinkUrl($assessor))->assertAccepted();
    $this->postJson(resetLinkUrl($assessor))
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'user.password_reset_recently_sent');

    Notification::assertSentToTimes($assessor, PasswordResetLinkNotification::class, 1);
});

it('refuses a reset link for a suspended user', function (): void {
    $administrator = systemAdmin();
    $suspended = User::factory()->withRole(RoleKey::Assessor)->suspended()->create();
    signIn($administrator);

    $this->postJson(resetLinkUrl($suspended))
        ->assertConflict()
        ->assertJsonPath('code', 'state.transition_illegal')
        ->assertJsonPath('errors.attempted.0', 'send_password_reset');

    Notification::assertNothingSent();
});

it('refuses a reset link to the administrator\'s own account', function (): void {
    $administrator = systemAdmin();
    signIn($administrator);

    $this->postJson(resetLinkUrl($administrator))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'user.self');

    Notification::assertNothingSent();
});

it('denies a staff member without the users-manage permission', function (): void {
    $follower = User::factory()->withRole(RoleKey::RequestFollower)->create();
    $assessor = User::factory()->withRole(RoleKey::Assessor)->create();
    signIn($follower);

    $this->postJson(resetLinkUrl($assessor))->assertForbidden();

    Notification::assertNothingSent();
});

it('requires a signed-in session', function (): void {
    $assessor = User::factory()->withRole(RoleKey::Assessor)->create();

    $this->postJson(resetLinkUrl($assessor))->assertUnauthorized();
});
