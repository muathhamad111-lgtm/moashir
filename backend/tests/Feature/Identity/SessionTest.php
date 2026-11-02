<?php

declare(strict_types=1);

use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Models\Identity\UserSession;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

it('requires authentication for the session bootstrap', function (): void {
    $this->getJson('/api/v1/auth/session')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
});

it('returns the unified workspace of a multi-role user', function (): void {
    $user = staffUser(RoleKey::RequestFollower, RoleKey::FilteringOfficer);
    signIn($user);

    $response = $this->getJson('/api/v1/auth/session')
        ->assertOk()
        ->assertJsonPath('data.is_super_admin', false)
        ->assertJsonPath('data.committee_appointments', [])
        ->assertJsonPath('data.idle_timeout_minutes', 30)
        ->assertJsonPath('data.roles.0.key', 'request_follower')
        ->assertJsonPath('data.roles.0.label.en', 'Assessment Requests Follower')
        ->assertJsonPath('data.roles.1.key', 'filtering_officer');

    expect($response->json('data.permissions'))
        ->toContain('requests.assign', 'award.filtering.decide')
        ->not->toContain('users.manage', 'audit.view');
});

it('gives the Super Admin every permission key without any role', function (): void {
    signIn(superAdmin());

    $this->getJson('/api/v1/auth/session')
        ->assertOk()
        ->assertJsonPath('data.is_super_admin', true)
        ->assertJsonPath('data.roles', [])
        ->assertJsonPath('data.idle_timeout_minutes', 15)
        ->assertJsonCount(count(PermissionKey::cases()), 'data.permissions');
});

it('uses the 60-minute idle timeout for entity users', function (): void {
    signIn(entityUser());

    $this->getJson('/api/v1/auth/session')->assertJsonPath('data.idle_timeout_minutes', 60);
});

it('expires a staff session after 30 idle minutes', function (): void {
    signIn(systemAdmin());
    $this->getJson('/api/v1/auth/session')->assertOk();

    $this->travel(31)->minutes();

    $this->getJson('/api/v1/auth/session')->assertUnauthorized()->assertJsonPath('code', 'session.expired');
    $this->assertGuest('web');
});

it('expires a Super Admin session after 15 idle minutes', function (): void {
    signIn(superAdmin());

    $this->travel(16)->minutes();

    $this->getJson('/api/v1/auth/session')->assertUnauthorized()->assertJsonPath('code', 'session.expired');
});

it('keeps an active session alive and enforces the absolute timeout', function (): void {
    $user = entityUser();
    signIn($user);

    foreach (range(1, 13) as $interval) {
        $this->travel(55)->minutes();
        $this->getJson('/api/v1/auth/session')->assertOk();
    }

    $this->travel(55)->minutes();
    $this->getJson('/api/v1/auth/session')->assertUnauthorized()->assertJsonPath('code', 'session.expired');
});

it('treats a session without sign-in timestamps as expired', function (): void {
    $this->actingAs(entityUser(), 'web');

    $this->getJson('/api/v1/auth/session')->assertUnauthorized()->assertJsonPath('code', 'session.expired');
});

it('signs out and invalidates the session', function (): void {
    signIn(entityUser());

    $this->postJson('/api/v1/auth/logout')->assertNoContent();
    $this->assertGuest('web');
});

it('rejects sign-out without a session', function (): void {
    $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
});

it('allows only the session bootstrap and the password change while a new password is required', function (): void {
    $user = entityUser();
    $user->forceFill(['password_change_required' => true])->save();
    signIn($user);

    $this->getJson('/api/v1/auth/session')->assertOk()->assertJsonPath('data.password_change_required', true);
    $this->getJson('/api/v1/me/preferences')->assertForbidden()->assertJsonPath('code', 'password.change_required');
});

it('lists and revokes the user own sessions only', function (): void {
    $user = entityUser();
    $other = entityUser();
    $now = Date::now()->getTimestamp();

    DB::table('sessions')->insert([
        ['id' => 'session-own-laptop', 'user_id' => $user->id, 'ip_address' => '10.0.0.10', 'user_agent' => 'Safari on macOS', 'payload' => '', 'last_activity' => $now],
        ['id' => 'session-own-phone', 'user_id' => $user->id, 'ip_address' => '10.0.0.11', 'user_agent' => 'Chrome on Android', 'payload' => '', 'last_activity' => $now - 60],
        ['id' => 'session-other-user', 'user_id' => $other->id, 'ip_address' => '10.0.0.12', 'user_agent' => 'Edge on Windows', 'payload' => '', 'last_activity' => $now],
    ]);
    signIn($user);

    $sessions = $this->getJson('/api/v1/me/sessions')->assertOk()->assertJsonCount(2, 'data')->json('data');

    expect($sessions[0])->toHaveKeys(['id', 'ip', 'user_agent', 'last_activity_at', 'current'])
        ->and($sessions[0]['id'])->not->toBe('session-own-laptop');

    $otherPublicId = UserSession::query()->findOrFail('session-other-user')->publicId();
    $this->deleteJson('/api/v1/me/sessions/'.$otherPublicId)->assertNotFound();

    $this->deleteJson('/api/v1/me/sessions/'.$sessions[1]['id'])->assertNoContent();
    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(1);

    $this->deleteJson('/api/v1/me/sessions')->assertOk()->assertJsonPath('data.revoked', 1);
    expect(DB::table('sessions')->where('user_id', $other->id)->count())->toBe(1);
});

it('reads and updates the personal preferences', function (): void {
    $user = entityUser();
    signIn($user);

    $this->getJson('/api/v1/me/preferences')->assertOk()->assertExactJson(['data' => ['locale' => 'ar', 'theme' => 'system'], 'meta' => [], 'errors' => null]);
    $this->patchJson('/api/v1/me/preferences', ['locale' => 'en', 'theme' => 'dark'])->assertOk()->assertJsonPath('data.locale', 'en');

    expect($user->refresh()->locale->value)->toBe('en');
    $this->patchJson('/api/v1/me/preferences', ['locale' => 'fr'])->assertUnprocessable()->assertJsonValidationErrors('locale');
});

it('lists the user own roles and permissions with bilingual labels', function (): void {
    signIn(staffUser(RoleKey::ContentOfficer));

    $response = $this->getJson('/api/v1/me/profile/permissions')
        ->assertOk()
        ->assertJsonPath('data.roles.0.key', 'content_officer')
        ->assertJsonStructure(['data' => ['permissions' => [['key', 'module', 'label' => ['ar', 'en']]]]]);

    expect(collect($response->json('data.permissions'))->pluck('key')->all())
        ->toContain('content.kpis.manage', 'ai.kb.manage')
        ->not->toContain('ai.provider.manage');
});
