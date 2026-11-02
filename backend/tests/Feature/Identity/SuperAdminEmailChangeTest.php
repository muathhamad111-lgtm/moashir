<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\EmailChangeStatus;
use App\Models\Audit\Audit;
use App\Models\Identity\EmailChangeRequest;
use App\Models\Identity\User;
use App\Notifications\Identity\OtpCodeNotification;
use App\Notifications\Identity\SuperAdminEmailChangedNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

const NEW_SUPER_ADMIN_EMAIL = 'platform.owner@moashir.example.sa';

function codeSentTo(string $email): string
{
    $code = '';

    Notification::assertSentOnDemand(OtpCodeNotification::class, function (OtpCodeNotification $notification, array $channels, AnonymousNotifiable $notifiable) use ($email, &$code): bool {
        $code = (string) ($notification->toMail($notifiable)->introLines[1] ?? '');

        return $notifiable->routeNotificationFor('mail') === $email;
    });

    return $code;
}

beforeEach(function (): void {
    Notification::fake();
});

it('denies the own e-mail change to everyone except the Super Admin', function (): void {
    signIn(systemAdmin());

    $this->postJson('/api/v1/me/email/change', ['new_email' => NEW_SUPER_ADMIN_EMAIL])->assertForbidden();
});

it('changes the Super Admin e-mail only after the OTP sent to the new address is verified', function (): void {
    $superAdmin = superAdmin();
    $oldEmail = $superAdmin->email;
    DB::table('sessions')->insert(['id' => 'super-admin-laptop', 'user_id' => $superAdmin->id, 'payload' => '', 'last_activity' => time()]);
    signIn($superAdmin);

    $challengeId = $this->postJson('/api/v1/me/email/change', ['new_email' => NEW_SUPER_ADMIN_EMAIL])
        ->assertOk()
        ->assertJsonPath('data.channel', 'email')
        ->json('data.challenge_id');

    expect($superAdmin->refresh()->email)->toBe($oldEmail);

    $this->postJson('/api/v1/me/email/confirm', ['challenge_id' => $challengeId, 'code' => codeSentTo(NEW_SUPER_ADMIN_EMAIL)])->assertNoContent();

    $superAdmin->refresh();
    expect($superAdmin->email)->toBe(NEW_SUPER_ADMIN_EMAIL)
        ->and($superAdmin->password_change_required)->toBeTrue()
        ->and($superAdmin->hasConfirmedTotp())->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $superAdmin->id)->exists())->toBeFalse()
        ->and(EmailChangeRequest::query()->firstOrFail()->status)->toBe(EmailChangeStatus::Applied);
    $this->assertGuest('web');

    $audit = Audit::query()->where('event', AuditEvent::SuperAdminEmailChanged->value)->firstOrFail();
    expect(json_encode($audit->getAttributes()))->not->toContain($oldEmail)->not->toContain(NEW_SUPER_ADMIN_EMAIL);

    Notification::assertSentOnDemand(SuperAdminEmailChangedNotification::class, static fn ($notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routeNotificationFor('mail') === [$oldEmail, NEW_SUPER_ADMIN_EMAIL]);
});

it('requires a new password at the next sign-in after the e-mail change', function (): void {
    $superAdmin = superAdmin();
    $superAdmin->forceFill(['password_change_required' => true])->save();
    signIn($superAdmin);

    $this->getJson('/api/v1/users')->assertForbidden()->assertJsonPath('code', 'password.change_required');
});

it('rejects a wrong confirmation code and keeps the old e-mail', function (): void {
    $superAdmin = superAdmin();
    signIn($superAdmin);
    $challengeId = $this->postJson('/api/v1/me/email/change', ['new_email' => NEW_SUPER_ADMIN_EMAIL])->json('data.challenge_id');
    $wrong = codeSentTo(NEW_SUPER_ADMIN_EMAIL) === '000000' ? '111111' : '000000';

    $this->postJson('/api/v1/me/email/confirm', ['challenge_id' => $challengeId, 'code' => $wrong])->assertUnprocessable()->assertJsonPath('code', 'otp.invalid');
    expect($superAdmin->refresh()->email)->not->toBe(NEW_SUPER_ADMIN_EMAIL);
});

it('rejects an e-mail that already belongs to another account', function (): void {
    $other = User::factory()->create();
    signIn(superAdmin());

    $this->postJson('/api/v1/me/email/change', ['new_email' => mb_strtoupper($other->email)])->assertUnprocessable()->assertJsonValidationErrors('new_email');
});
