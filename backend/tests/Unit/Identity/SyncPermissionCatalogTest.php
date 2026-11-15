<?php

declare(strict_types=1);

use App\Actions\Identity\SyncPermissionCatalog;
use App\Models\Identity\Permission;
use App\Models\Identity\Role;

it('is idempotent', function (): void {
    app(SyncPermissionCatalog::class)->handle();
    app(SyncPermissionCatalog::class)->handle();

    expect(Permission::query()->count())->toBe(88)
        ->and(Role::query()->count())->toBe(9)
        ->and(Role::query()->where('name', 'system_admin')->firstOrFail()->permissions()->count())->toBe(63);
});

it('keeps the labels and matrix changes made by administrators', function (): void {
    $permission = Permission::query()->where('name', 'users.view')->firstOrFail();
    $permission->forceFill(['label' => ['ar' => 'تسمية معدلة', 'en' => 'Edited label']])->save();
    $role = Role::query()->where('name', 'content_officer')->firstOrFail();
    $role->revokePermissionTo('content.kpis.manage');

    app(SyncPermissionCatalog::class)->handle();

    expect($permission->refresh()->label->en)->toBe('Edited label')
        ->and($role->refresh()->permissionKeys())->not->toContain('content.kpis.manage');
});

it('grants a newly added key to its default roles only', function (): void {
    Permission::query()->where('name', 'audit.export')->firstOrFail()->roles()->detach();
    DB::table('permissions')->where('name', 'audit.export')->delete();

    app(SyncPermissionCatalog::class)->handle();

    expect(Role::query()->where('name', 'system_admin')->firstOrFail()->permissionKeys())->toContain('audit.export')
        ->and(Role::query()->where('name', 'finance_officer')->firstOrFail()->permissionKeys())->not->toContain('audit.export');
});

it('always restores the code-defined entity permissions', function (): void {
    $entity = Role::query()->where('name', 'entity')->firstOrFail();
    $entity->givePermissionTo('users.manage');

    app(SyncPermissionCatalog::class)->handle();

    expect($entity->refresh()->permissionKeys())->not->toContain('users.manage');
});

it('seeds the bilingual labels of every key from the translation files', function (): void {
    foreach (Permission::query()->get() as $permission) {
        expect($permission->label->en)->not->toStartWith('permissions.')
            ->and($permission->label->ar)->not->toStartWith('permissions.')
            ->and($permission->description?->en)->not->toBeEmpty();
    }

    $label = Permission::query()->where('name', 'roles.matrix.manage')->firstOrFail()->label;

    expect($label->ar)->toBe('إدارة الأدوار والصلاحيات')->and($label->en)->toBe('Manage roles and permissions');
});
