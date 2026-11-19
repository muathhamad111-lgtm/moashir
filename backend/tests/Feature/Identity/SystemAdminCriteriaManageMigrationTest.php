<?php

declare(strict_types=1);

use App\Models\Identity\Permission;
use App\Models\Identity\Role;

function systemAdminCriteriaManageMigration(): object
{
    return require database_path('migrations/2026_11_19_210000_grant_criteria_manage_to_system_admin.php');
}

/**
 * @return list<string>
 */
function systemAdminRoleKeys(): array
{
    return Role::query()->where('name', 'system_admin')->firstOrFail()->refresh()->permissionKeys();
}

it('gives the existing System Administrator role criteria.manage and is safe to repeat', function (): void {
    Role::query()->where('name', 'system_admin')->firstOrFail()->revokePermissionTo('criteria.manage');
    expect(systemAdminRoleKeys())->not->toContain('criteria.manage');

    systemAdminCriteriaManageMigration()->up();
    systemAdminCriteriaManageMigration()->up();

    expect(systemAdminRoleKeys())->toContain('criteria.manage');
});

it('removes the grant on rollback and leaves the Criteria Officer untouched', function (): void {
    systemAdminCriteriaManageMigration()->down();

    expect(systemAdminRoleKeys())->not->toContain('criteria.manage')
        ->and(Role::query()->where('name', 'criteria_officer')->firstOrFail()->permissionKeys())->toContain('criteria.manage');
});

it('does nothing on a database without the catalog', function (): void {
    Permission::query()->where('name', 'criteria.manage')->firstOrFail()->roles()->detach();
    Permission::query()->where('name', 'criteria.manage')->delete();

    systemAdminCriteriaManageMigration()->up();

    expect(Permission::query()->where('name', 'criteria.manage')->exists())->toBeFalse();
});
