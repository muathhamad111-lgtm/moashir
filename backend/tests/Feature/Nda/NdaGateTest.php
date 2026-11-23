<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Enums\Settings\SettingKey;
use App\Models\Identity\TermsAcceptance;
use App\Services\Settings\SettingsStore;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

it('refuses an in-scope user who has not accepted the current version on every signed-in route', function (): void {
    publishedNda();
    $admin = systemAdmin();

    signIn($admin)->getJson('/api/v1/users')
        ->assertForbidden()
        ->assertJsonPath('code', 'nda.acceptance_required');
    signIn($admin)->getJson('/api/v1/me/preferences')->assertForbidden()->assertJsonPath('code', 'nda.acceptance_required');
});

it('keeps the session, sign-out and the agreement itself reachable while the gate is closed', function (): void {
    $version = publishedNda();
    $assessor = staffUser(RoleKey::Assessor);

    signIn($assessor)->getJson('/api/v1/auth/session')->assertOk()->assertJsonPath('data.nda_acceptance_required', true);
    signIn($assessor)->getJson('/api/v1/me/nda')
        ->assertOk()
        ->assertJsonPath('data.acceptance_required', true)
        ->assertJsonPath('data.version.id', $version->id)
        ->assertJsonPath('data.version.change_summary.ar.0.type', 'paragraph');
    signIn($assessor)->postJson('/api/v1/me/nda/acceptances', ['terms_version_id' => $version->id])->assertOk();
    signIn($assessor)->getJson('/api/v1/me/preferences')->assertOk();
    signIn($assessor)->getJson('/api/v1/auth/session')->assertJsonPath('data.nda_acceptance_required', false);
    signIn($assessor)->postJson('/api/v1/auth/logout')->assertSuccessful();
});

it('leaves out-of-scope staff and entity users alone', function (RoleKey $role): void {
    publishedNda();
    $user = $role === RoleKey::Entity ? entityUser() : staffUser($role);

    signIn($user)->getJson('/api/v1/auth/session')->assertOk()->assertJsonPath('data.nda_acceptance_required', false);
    signIn($user)->getJson('/api/v1/me/preferences')->assertOk();
})->with([RoleKey::FinanceOfficer, RoleKey::ContentOfficer, RoleKey::Entity]);

it('gates the Super Admin too, whatever the roles in scope', function (): void {
    publishedNda();
    app(SettingsStore::class)->put(SettingKey::NdaRoles, [], AuditEvent::NdaScopeChanged, null);

    signIn(superAdmin())->getJson('/api/v1/users')->assertForbidden()->assertJsonPath('code', 'nda.acceptance_required');
});

it('gates nobody while no version has been published', function (): void {
    ndaDraft();

    signIn(systemAdmin())->getJson('/api/v1/users')->assertOk();
});

it('asks for a new password before the agreement', function (): void {
    publishedNda();
    $admin = systemAdmin();
    $admin->forceFill(['password_change_required' => true])->save();

    signIn($admin)->getJson('/api/v1/users')->assertForbidden()->assertJsonPath('code', 'password.change_required');
});

it('gates the holders of a role added to the scope and keeps acceptances when a role leaves it', function (): void {
    $version = publishedNda();
    $finance = staffUser(RoleKey::FinanceOfficer);
    $assessor = staffUser(RoleKey::Assessor);
    acceptNdaAs($assessor, $version);
    $store = app(SettingsStore::class);

    $store->put(SettingKey::NdaRoles, [RoleKey::FinanceOfficer->value], AuditEvent::NdaScopeChanged, null);

    signIn($finance)->getJson('/api/v1/me/preferences')->assertForbidden()->assertJsonPath('code', 'nda.acceptance_required');
    expect(TermsAcceptance::query()->where('user_id', $assessor->id)->where('terms_version_id', $version->id)->exists())->toBeTrue();
    switchTo($assessor)->getJson('/api/v1/me/preferences')->assertOk();
});

it('runs the agreement gate on every signed-in route except the session, sign-out and the agreement', function (): void {
    $exempt = ['auth.session', 'auth.logout', 'auth.password.change', 'me.nda.show', 'me.nda.accept'];
    $missing = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        /** @var RoutingRoute $route */
        $middleware = $route->gatherMiddleware();

        if (in_array('auth:sanctum', $middleware, true) && ! in_array('nda.current', $middleware, true) && ! in_array($route->getName(), $exempt, true)) {
            $missing[] = $route->getName() ?? $route->uri();
        }
    }

    expect($missing)->toBe([]);
});

it('runs the agreement gate after the MFA and password checks', function (): void {
    $route = Route::getRoutes()->getByName('users.index');
    $middleware = $route?->gatherMiddleware() ?? [];

    expect(array_search('nda.current', $middleware, true))->toBeGreaterThan(array_search('password.current', $middleware, true))
        ->and(array_search('password.current', $middleware, true))->toBeGreaterThan(array_search('mfa', $middleware, true));
});
