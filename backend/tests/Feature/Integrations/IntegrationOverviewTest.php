<?php

declare(strict_types=1);

use App\Enums\Identity\RoleKey;
use App\Enums\Integrations\IntegrationMode;

const INTEGRATIONS_OVERVIEW = '/api/v1/settings/integrations';

it('lists every integration kind with its provider, state, source and last test', function (): void {
    $admin = systemAdmin();
    configureMoyasar(IntegrationMode::Test, [...passedTestState(), 'is_active' => true, 'last_test_by_user_id' => $admin->id]);
    configureTaqnyat();
    signIn($admin);

    $this->getJson(INTEGRATIONS_OVERVIEW)
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0.kind', 'payment')
        ->assertJsonPath('data.0.provider', 'moyasar')
        ->assertJsonPath('data.0.active', true)
        ->assertJsonPath('data.0.mode', 'test')
        ->assertJsonPath('data.0.source', 'settings')
        ->assertJsonPath('data.0.configured', true)
        ->assertJsonPath('data.0.last_test.ok', true)
        ->assertJsonPath('data.0.last_test.by', ['id' => $admin->id, 'name' => $admin->name])
        ->assertJsonPath('data.1', ['kind' => 'sms', 'provider' => null, 'active' => false, 'mode' => 'live', 'source' => 'settings', 'configured' => true, 'last_test' => null])
        ->assertJsonPath('data.2', ['kind' => 'email', 'provider' => 'smtp', 'active' => false, 'mode' => 'live', 'source' => 'none', 'configured' => false, 'last_test' => null])
        ->assertJsonPath('data.3.kind', 'ai')
        ->assertJsonPath('meta.payment_test_mode_in_production', false);
});

it('reports environment bootstrap credentials as the source until the first save', function (): void {
    config()->set('moashir.integrations.env_fallback', true);
    config()->set('moashir.integrations.environment.sms', [
        'provider' => 'taqnyat',
        'taqnyat' => ['bearer_token' => FAKE_TAQNYAT_TOKEN, 'sender_name' => 'Moashir'],
        'msegat' => ['username' => null, 'api_key' => null, 'sender_name' => null],
    ]);
    signIn(systemAdmin());

    $this->getJson(INTEGRATIONS_OVERVIEW)->assertOk()->assertJsonPath('data.1.source', 'environment')->assertJsonPath('data.1.provider', 'taqnyat')->assertJsonPath('data.1.active', true);

    $response = $this->getJson('/api/v1/settings/sms')
        ->assertOk()
        ->assertJsonPath('data.source', 'environment')
        ->assertJsonPath('data.providers.taqnyat.bearer_token', ['set' => true, 'last4' => null, 'updated_at' => null, 'updated_by' => null]);
    assertNoSecretIn((string) $response->getContent());

    $this->putJson('/api/v1/settings/sms', ['providers' => ['msegat' => ['username' => 'moashir-account']]])->assertOk()->assertJsonPath('data.source', 'settings');
});

it('ignores the environment when the bootstrap switch is off', function (): void {
    config()->set('moashir.integrations.environment.sms.provider', 'taqnyat');
    config()->set('moashir.integrations.environment.sms.taqnyat', ['bearer_token' => FAKE_TAQNYAT_TOKEN, 'sender_name' => 'Moashir']);
    signIn(systemAdmin());

    $this->getJson(INTEGRATIONS_OVERVIEW)->assertOk()->assertJsonPath('data.1.source', 'none');
});

it('lets the Super Admin see the overview and denies every other role', function (RoleKey $role): void {
    signIn(staffUser($role));

    $this->getJson(INTEGRATIONS_OVERVIEW)->assertForbidden()->assertJsonPath('code', 'forbidden');
})->with(nonSystemAdminRoles());

it('serves the overview to the Super Admin and refuses entities and guests', function (): void {
    $this->getJson(INTEGRATIONS_OVERVIEW)->assertUnauthorized();

    signIn(entityUser());
    $this->getJson(INTEGRATIONS_OVERVIEW)->assertForbidden();

    switchTo(superAdmin());
    $this->getJson(INTEGRATIONS_OVERVIEW)->assertOk()->assertJsonCount(4, 'data');
});

it('hard-locks the integration permissions to the system administrator', function (string $key): void {
    signIn(systemAdmin());

    $this->putJson('/api/v1/roles/finance_officer/permissions', ['permission_keys' => ['dashboard.view', $key]])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'permission.locked');

    $permission = collect($this->getJson('/api/v1/permissions')->assertOk()->json('data.permissions'))->firstWhere('key', $key);

    expect($permission['locked_to_roles'])->toBe(['system_admin']);
})->with(['settings.payment.manage', 'settings.sms.manage', 'settings.email.manage', 'ai.provider.manage']);
