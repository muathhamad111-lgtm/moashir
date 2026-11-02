<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Models\Audit\Audit;
use App\Models\Identity\Permission;
use App\Models\Identity\Role;

it('returns the code-defined catalog, the roles and the matrix', function (): void {
    signIn(systemAdmin());

    $response = $this->getJson('/api/v1/permissions')->assertOk();

    expect($response->json('data.permissions'))->toHaveCount(count(PermissionKey::cases()))
        ->and(collect($response->json('data.roles'))->pluck('key')->all())->not->toContain('super_admin')
        ->and(collect($response->json('data.matrix'))->keys()->sort()->values()->all())->toBe(collect(RoleKey::values())->sort()->values()->all())
        ->and($response->json('data.matrix.finance_officer'))->not->toContain('audit.view');

    $branding = collect($response->json('data.permissions'))->firstWhere('key', 'settings.branding.manage');
    expect($branding['locked_to_roles'])->toBe(['system_admin'])
        ->and(collect($response->json('data.permissions'))->firstWhere('key', 'messaging.campaigns.send')['grantable'])->toBeTrue();
});

it('filters the catalog by module', function (): void {
    signIn(systemAdmin());

    $keys = collect($this->getJson('/api/v1/permissions?filter[module]=admin')->assertOk()->json('data.permissions'))->pluck('key')->all();

    expect($keys)->toBe(['users.view', 'users.manage', 'roles.matrix.view', 'roles.matrix.manage', 'audit.view', 'audit.export']);
    $this->getJson('/api/v1/permissions?filter[module]=support')->assertUnprocessable()->assertJsonValidationErrors('filter.module');
});

it('denies the matrix to roles without the matrix permission', function (RoleKey $role): void {
    signIn(staffUser($role));

    $this->getJson('/api/v1/permissions')->assertForbidden();
    $this->putJson('/api/v1/roles/assessor/permissions', ['permission_keys' => []])->assertForbidden();
})->with([[RoleKey::Assessor], [RoleKey::FinanceOfficer], [RoleKey::ContentOfficer]]);

it('replaces the permissions of a role and audits the change', function (): void {
    signIn(systemAdmin());
    $keys = ['dashboard.view', 'notifications.view', 'content.home.manage'];

    $this->putJson('/api/v1/roles/content_officer/permissions', ['permission_keys' => $keys])
        ->assertOk()
        ->assertJsonPath('data.role.key', 'content_officer')
        ->assertJsonPath('data.permission_keys', ['content.home.manage', 'dashboard.view', 'notifications.view']);

    $audit = Audit::query()->where('event', AuditEvent::RolePermissionsChanged->value)->firstOrFail();
    expect($audit->old_values['permissions'] ?? [])->toContain('content.kpis.manage')
        ->and($audit->new_values['permissions'] ?? [])->not->toContain('content.kpis.manage');
});

it('grants an independent permission to another role', function (): void {
    signIn(systemAdmin());
    $current = Role::query()->where('name', 'request_follower')->firstOrFail()->permissionKeys();

    $this->putJson('/api/v1/roles/request_follower/permissions', ['permission_keys' => [...$current, 'entities.verification.resend']])->assertOk();

    expect(staffUser(RoleKey::RequestFollower)->can('entities.verification.resend'))->toBeTrue();
});

it('rejects unknown permission keys', function (): void {
    signIn(systemAdmin());

    $this->putJson('/api/v1/roles/assessor/permissions', ['permission_keys' => ['dashboard.view', 'support.tickets.manage']])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'permission.unknown')
        ->assertJsonPath('errors.permission_keys', ['support.tickets.manage']);
});

it('keeps the hard-locked branding permission on the system administrator only', function (): void {
    $administrator = systemAdmin();
    signIn(staffUser(RoleKey::SystemAdmin));

    $this->putJson('/api/v1/roles/content_officer/permissions', ['permission_keys' => ['dashboard.view', 'settings.branding.manage']])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'permission.locked');

    expect($administrator->can('settings.branding.manage'))->toBeTrue();
});

it('refuses to edit the entity role', function (): void {
    signIn(systemAdmin());

    $this->putJson('/api/v1/roles/entity/permissions', ['permission_keys' => ['dashboard.view']])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'role.not_editable');
});

it('enforces SoD-7: a matrix manager cannot change a role they hold', function (): void {
    signIn(systemAdmin());

    $this->putJson('/api/v1/roles/system_admin/permissions', ['permission_keys' => ['dashboard.view', 'settings.branding.manage']])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'sod_self_grant');
});

it('answers 404 for a super_admin role because it is not a role', function (): void {
    signIn(superAdmin());

    $this->putJson('/api/v1/roles/super_admin/permissions', ['permission_keys' => []])->assertNotFound();
});

it('validates the matrix payload', function (): void {
    signIn(systemAdmin());

    $this->putJson('/api/v1/roles/assessor/permissions', [])->assertUnprocessable()->assertJsonValidationErrors('permission_keys');
    $this->putJson('/api/v1/roles/assessor/permissions', ['permission_keys' => ['dashboard.view', 'dashboard.view']])->assertUnprocessable();
});

it('updates the bilingual labels of a permission without changing its key', function (): void {
    signIn(systemAdmin());

    $this->putJson('/api/v1/permissions/users.view', [
        'label' => ['ar' => 'استعراض حسابات المستخدمين', 'en' => 'Browse user accounts'],
        'description' => ['ar' => 'عرض قائمة الحسابات وتفاصيلها.', 'en' => 'See the account list and details.'],
    ])
        ->assertOk()
        ->assertJsonPath('data.key', 'users.view')
        ->assertJsonPath('data.label.en', 'Browse user accounts');

    expect(Audit::query()->where('auditable_type', Permission::class)->where('event', 'updated')->exists())->toBeTrue();
});

it('requires both languages for permission labels', function (): void {
    signIn(systemAdmin());

    $this->putJson('/api/v1/permissions/users.view', ['label' => ['ar' => 'استعراض حسابات المستخدمين']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('label.en');
});

it('offers no endpoint to create or delete permission keys', function (): void {
    signIn(systemAdmin());

    $this->postJson('/api/v1/permissions', ['key' => 'reports.custom.view'])->assertStatus(405);
    $this->deleteJson('/api/v1/permissions/users.view')->assertStatus(405);
});
