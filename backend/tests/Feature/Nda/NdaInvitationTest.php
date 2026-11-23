<?php

declare(strict_types=1);

use App\Models\Identity\TermsAcceptance;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use App\Notifications\Identity\StaffInvitationNotification;
use App\Services\Identity\SessionKeys;
use Illuminate\Support\Facades\Notification;

const NDA_INVITEE_PASSWORD = 'Tuwaiq-Mountain-2026';

function ndaInviteToken(string $role, string $email = 'nda.invitee@moashir.example.sa'): string
{
    $admin = systemAdmin();
    $current = TermsVersion::query()->current()->where('kind', 'nda')->first();

    if ($current instanceof TermsVersion) {
        acceptNdaAs($admin, $current);
    }

    test()->actingAs($admin, 'web')
        ->withSession([SessionKeys::SIGNED_IN_AT => time(), SessionKeys::LAST_SEEN_AT => time()])
        ->postJson('/api/v1/users', ['name' => 'سلمان المطيري', 'gender' => 'male', 'email' => $email, 'roles' => [$role]])
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

/**
 * @return array<string, mixed>
 */
function ndaInvitationPayload(array $extra = []): array
{
    return ['password' => NDA_INVITEE_PASSWORD, 'password_confirmation' => NDA_INVITEE_PASSWORD, ...$extra];
}

beforeEach(function (): void {
    Notification::fake();
});

it('shows the agreement to an invitee whose roles are in scope and records it with the invitation', function (): void {
    $version = publishedNda();
    $token = ndaInviteToken('assessor');

    $this->getJson("/api/v1/auth/invitations/{$token}")->assertOk()->assertJsonPath('data.nda.id', $version->id);
    $this->postJson("/api/v1/auth/invitations/{$token}/accept", ndaInvitationPayload(['nda_version_id' => $version->id]))->assertOk();

    $invitee = User::query()->where('email', 'nda.invitee@moashir.example.sa')->sole();
    expect(TermsAcceptance::query()->where('user_id', $invitee->id)->where('terms_version_id', $version->id)->sole()->accepted_roles)->toBe(['assessor']);
});

it('refuses the invitation without the agreement when the roles are in scope', function (): void {
    $version = publishedNda();
    $token = ndaInviteToken('assessor');

    $this->postJson("/api/v1/auth/invitations/{$token}/accept", ndaInvitationPayload())->assertUnprocessable()->assertJsonValidationErrors('nda_version_id');
    $this->postJson("/api/v1/auth/invitations/{$token}/accept", ndaInvitationPayload(['nda_version_id' => $version->id + 1]))->assertUnprocessable()->assertJsonValidationErrors('nda_version_id');
});

it('asks nothing of an invitee whose roles are out of scope', function (): void {
    publishedNda();
    $token = ndaInviteToken('finance_officer');

    $this->getJson("/api/v1/auth/invitations/{$token}")->assertOk()->assertJsonPath('data.nda', null);
    $this->postJson("/api/v1/auth/invitations/{$token}/accept", ndaInvitationPayload())->assertOk();
});

it('tells an in-scope invitee in the invitation e-mail that accepting includes the agreement', function (string $role, bool $mentioned): void {
    publishedNda();
    ndaInviteToken($role);

    $invitee = User::query()->where('email', 'nda.invitee@moashir.example.sa')->sole();
    Notification::assertSentTo($invitee, StaffInvitationNotification::class, function (StaffInvitationNotification $notification) use ($invitee, $mentioned): bool {
        $lines = implode(' ', $notification->toMail($invitee)->introLines);

        return str_contains($lines, __('notifications.staff_invited.nda')) === $mentioned;
    });
})->with([['assessor', true], ['finance_officer', false]]);
