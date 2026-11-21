<?php

declare(strict_types=1);

use App\Actions\Identity\IssueInvitation;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\InvitationStatus;
use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Enums\Identity\UserStatus;
use App\Models\Audit\Audit;
use App\Models\Identity\Role;
use App\Models\Identity\User;
use App\Models\Identity\UserInvitation;
use App\Models\Identity\UserSession;
use App\Services\Identity\SecretHasher;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    Notification::fake();
});

function pendingInvitee(User $administrator, UserStatus $status = UserStatus::Invited): User
{
    $invitee = User::factory()->withRole(RoleKey::Assessor)->invited()->create(['email' => 'new.assessor@moashir.example.sa', 'phone' => '+966551112233']);
    app(IssueInvitation::class)->handle($administrator, $invitee);
    $invitee->forceFill(['status' => $status])->save();

    return $invitee->refresh();
}

function openSessionFor(User $user, string $id): void
{
    UserSession::query()->forceCreate(['id' => $id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->getTimestamp()]);
}

function revokeUsersPermission(RoleKey $role, PermissionKey $permission): void
{
    Role::findByName($role->value, 'web')->revokePermissionTo($permission->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

it('cancels a pending invitation, revokes its link, removes the account and audits it first with a masked e-mail', function (UserStatus $status): void {
    $administrator = systemAdmin();
    $invitee = pendingInvitee($administrator, $status);
    signIn($administrator);
    $auditsBefore = (int) Audit::query()->max('id');

    $this->deleteJson("/api/v1/users/{$invitee->id}/invitation")->assertNoContent();

    $removed = User::withTrashed()->findOrFail($invitee->id);
    $audit = Audit::query()->where('event', AuditEvent::UserInvitationCancelled->value)->sole();

    expect($removed->trashed())->toBeTrue()
        ->and($removed->email)->not->toContain('new.assessor')
        ->and($removed->phone)->toBeNull()
        ->and($removed->roles()->count())->toBe(0)
        ->and(UserInvitation::query()->where('user_id', $invitee->id)->pluck('status')->all())->toBe([InvitationStatus::Revoked])
        ->and($audit->auditable_id)->toBe($invitee->id)
        ->and($audit->user_id)->toBe($administrator->id)
        ->and($audit->old_values)->toEqual(['status' => $status->value, 'email' => 'n***@moashir.example.sa'])
        ->and(json_encode(Audit::query()->where('id', '>', $auditsBefore)->get(['event', 'old_values', 'new_values'])->toArray()))->not->toContain('new.assessor@moashir.example.sa');
    $this->getJson("/api/v1/users/{$invitee->id}")->assertNotFound();
})->with([
    'invited' => [UserStatus::Invited],
    'expired' => [UserStatus::Expired],
]);

it('lets the same address be invited again after a cancellation', function (): void {
    $administrator = systemAdmin();
    $invitee = pendingInvitee($administrator);
    signIn($administrator);

    $this->deleteJson("/api/v1/users/{$invitee->id}/invitation")->assertNoContent();

    $this->postJson('/api/v1/users', ['name' => 'سلمان المطيري', 'gender' => 'male', 'email' => 'new.assessor@moashir.example.sa', 'roles' => ['assessor']])->assertCreated();
});

it('makes the cancelled invitation link unusable', function (): void {
    $administrator = systemAdmin();
    $invitee = pendingInvitee($administrator);
    $token = 'known-token-'.str_repeat('a', 52);
    $invitee->invitations()->update(['token_hash' => app(SecretHasher::class)->hash($token)]);
    signIn($administrator)->deleteJson("/api/v1/users/{$invitee->id}/invitation")->assertNoContent();
    app('auth')->guard('web')->logout();

    $this->postJson("/api/v1/auth/invitations/{$token}/accept", ['password' => 'Tuwaiq-Mountain-2026', 'password_confirmation' => 'Tuwaiq-Mountain-2026'])->assertNotFound();
});

it('refuses to cancel the invitation of an account that is not waiting for one', function (Closure $target, string $state): void {
    signIn(systemAdmin());
    $user = $target();

    $this->deleteJson("/api/v1/users/{$user->id}/invitation")
        ->assertConflict()
        ->assertJsonPath('code', 'state.transition_illegal')
        ->assertJsonPath('errors.state.0', $state)
        ->assertJsonPath('errors.attempted.0', 'cancel_invitation');

    expect(User::query()->whereKey($user->id)->exists())->toBeTrue()
        ->and(Audit::query()->where('event', AuditEvent::UserInvitationCancelled->value)->exists())->toBeFalse();
})->with([
    'active' => [fn (): User => User::factory()->withRole(RoleKey::Assessor)->create(), 'active'],
    'suspended' => [fn (): User => User::factory()->withRole(RoleKey::Assessor)->suspended()->create(), 'suspended'],
]);

it('refuses to cancel an invitation without the users management permission', function (): void {
    $administrator = systemAdmin();
    $invitee = pendingInvitee($administrator);
    signIn(staffUser(RoleKey::FinanceOfficer));

    $this->deleteJson("/api/v1/users/{$invitee->id}/invitation")->assertForbidden();

    expect(User::query()->whereKey($invitee->id)->exists())->toBeTrue();
});

it('hides a Super Admin from the invitation cancellation', function (): void {
    signIn(systemAdmin());

    $this->deleteJson('/api/v1/users/'.superAdmin()->id.'/invitation')->assertNotFound();
});

it('signs a user out everywhere, records the optional reason and audits it', function (): void {
    $administrator = systemAdmin();
    $target = staffUser(RoleKey::Assessor);
    $bystander = staffUser(RoleKey::Assessor);
    openSessionFor($target, 'target-laptop');
    openSessionFor($target, 'target-phone');
    openSessionFor($bystander, 'bystander-laptop');
    signIn($administrator);

    $this->postJson("/api/v1/users/{$target->id}/sessions/revoke", ['reason' => 'فقد المستخدم جهازه المحمول'])
        ->assertOk()
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.status', 'active');

    $audit = Audit::query()->where('event', AuditEvent::UserSessionsRevoked->value)->sole();

    expect(UserSession::query()->where('user_id', $target->id)->count())->toBe(0)
        ->and(UserSession::query()->where('user_id', $bystander->id)->count())->toBe(1)
        ->and($audit->auditable_id)->toBe($target->id)
        ->and($audit->new_values)->toBe(['sessions_ended' => 2])
        ->and($audit->reason)->toBe('فقد المستخدم جهازه المحمول');
});

it('signs out a user of any status, an entity account included', function (): void {
    $suspended = User::factory()->withRole(RoleKey::Assessor)->suspended()->create();
    $owner = ownerOf(entityWithOwner());
    openSessionFor($owner, 'owner-session');
    signIn(systemAdmin());

    $this->postJson("/api/v1/users/{$suspended->id}/sessions/revoke")->assertOk()->assertJsonPath('data.status', 'suspended');
    $this->postJson("/api/v1/users/{$owner->id}/sessions/revoke")->assertOk();

    expect(UserSession::query()->where('user_id', $owner->id)->exists())->toBeFalse();
});

it('refuses to sign out the acting administrator everywhere', function (): void {
    $administrator = systemAdmin();
    signIn($administrator);

    $this->postJson("/api/v1/users/{$administrator->id}/sessions/revoke")->assertUnprocessable()->assertJsonPath('code', 'user.self');
});

it('validates the optional reason and the permission of the sign-out', function (): void {
    $target = staffUser(RoleKey::Assessor);
    signIn(systemAdmin());
    $this->postJson("/api/v1/users/{$target->id}/sessions/revoke", ['reason' => 'قصير'])->assertUnprocessable()->assertJsonValidationErrors(['reason']);

    switchTo(staffUser(RoleKey::ContentOfficer))->postJson("/api/v1/users/{$target->id}/sessions/revoke")->assertForbidden();
    expect(Audit::query()->where('event', AuditEvent::UserSessionsRevoked->value)->exists())->toBeFalse();
});

it('exports the filtered users as a CSV with localized roles and status and audits the export without personal data', function (): void {
    $administrator = systemAdmin();
    $assessor = staffUser(RoleKey::Assessor);
    $assessor->forceFill(['name' => 'ماجد السبيعي', 'last_login_at' => '2026-11-10 09:30:00'])->save();
    User::factory()->withRole(RoleKey::FinanceOfficer)->suspended()->create();
    signIn($administrator);

    $response = $this->getJson('/api/v1/users/export?filter[role]=assessor&filter[status]=active')
        ->assertOk()
        ->assertHeader('X-Export-Row-Count', '1');

    $csv = $response->streamedContent();
    $lines = array_values(array_filter(explode("\n", trim(ltrim($csv, "\u{FEFF}")))));
    $audit = Audit::query()->where('event', AuditEvent::UsersExported->value)->sole();

    expect($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->headers->get('Content-Disposition'))->toContain('users-')
        ->and($csv)->toStartWith("\u{FEFF}")
        ->and($lines)->toHaveCount(2)
        ->and(str_getcsv($lines[0], ',', '"', ''))->toBe(['الاسم', 'البريد الإلكتروني', 'الأدوار', 'الحالة', 'آخر تسجيل دخول', 'تاريخ الإنشاء'])
        ->and(str_getcsv($lines[1], ',', '"', ''))->toBe([
            'ماجد السبيعي',
            $assessor->email,
            'المقيّم',
            'نشط',
            '2026-11-10T12:30:00+03:00',
            $assessor->created_at->copy()->setTimezone('Asia/Riyadh')->toIso8601String(),
        ])
        ->and($audit->user_id)->toBe($administrator->id)
        ->and($audit->new_values)->toEqual(['row_count' => 1, 'filters' => ['search' => false, 'role' => 'assessor', 'status' => 'active']])
        ->and(json_encode($audit->new_values))->not->toContain($assessor->email);
});

it('writes the user export in English for an English request and joins several roles', function (): void {
    $officer = staffUser(RoleKey::FinanceOfficer, RoleKey::ContentOfficer);
    signIn(systemAdmin());

    $csv = $this->getJson('/api/v1/users/export?search='.urlencode($officer->email), ['Accept-Language' => 'en'])->assertOk()->streamedContent();
    $lines = array_values(array_filter(explode("\n", trim(ltrim($csv, "\u{FEFF}")))));

    expect(str_getcsv($lines[0], ',', '"', ''))->toBe(['Name', 'E-mail', 'Roles', 'Status', 'Last sign-in', 'Created at'])
        ->and($csv)->toContain('"Finance Officer, Content Officer"')
        ->and(str_getcsv($lines[1], ',', '"', '')[4])->toBe('');
});

it('refuses the user export without the export permission or with invalid filters', function (): void {
    revokeUsersPermission(RoleKey::SystemAdmin, PermissionKey::UsersExport);
    signIn(systemAdmin());

    $this->getJson('/api/v1/users/export')->assertForbidden();
    expect(Audit::query()->where('event', AuditEvent::UsersExported->value)->exists())->toBeFalse();

    switchTo(staffUser(RoleKey::Assessor))->getJson('/api/v1/users/export')->assertForbidden();
});

it('validates the user export filters', function (): void {
    signIn(systemAdmin());

    $this->getJson('/api/v1/users/export?filter[status]=unknown')->assertUnprocessable()->assertJsonValidationErrors(['filter.status']);
});

it('rate limits the user export', function (): void {
    signIn(systemAdmin());

    foreach (range(1, 5) as $attempt) {
        $this->getJson('/api/v1/users/export')->assertOk();
    }

    $this->getJson('/api/v1/users/export')->assertTooManyRequests();
});

it('opens the activity log of a user filtered by that user as the actor', function (): void {
    $administrator = systemAdmin();
    $target = staffUser(RoleKey::Assessor);
    signIn($administrator);
    $this->postJson("/api/v1/users/{$target->id}/sessions/revoke")->assertOk();
    $this->getJson('/api/v1/audit-logs?filter[actor_id]='.$administrator->id)
        ->assertOk()
        ->assertJsonPath('data.0.event', AuditEvent::UserSessionsRevoked->value);
    $this->getJson('/api/v1/audit-logs?filter[actor_id]='.$target->id)->assertOk()->assertJsonCount(0, 'data');
});
