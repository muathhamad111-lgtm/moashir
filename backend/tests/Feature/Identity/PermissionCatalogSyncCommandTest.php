<?php

declare(strict_types=1);

use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Models\Identity\Permission;
use App\Models\Identity\Role;
use App\Services\Platform\DestructiveCommandBaseline;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Support\Facades\DB;

it('applies the code-defined permission catalog and is safe to repeat', function (): void {
    $this->artisan('moashir:permissions:sync')
        ->expectsOutputToContain(sprintf('%d permissions, %d roles', count(PermissionKey::cases()), count(RoleKey::cases())))
        ->assertSuccessful();

    $this->artisan('moashir:permissions:sync')->assertSuccessful();

    expect(Permission::query()->count())->toBe(count(PermissionKey::cases()))
        ->and(Role::query()->count())->toBe(count(RoleKey::cases()));
});

it('installs new catalog keys in production, where seeding is refused', function (): void {
    Permission::query()->where('name', 'audit.export')->firstOrFail()->roles()->detach();
    DB::table('permissions')->where('name', 'audit.export')->delete();
    app()->instance('env', 'production');
    app(DestructiveCommandBaseline::class)->apply();

    $this->artisan('db:seed', ['--class' => PermissionCatalogSeeder::class, '--force' => true])->assertFailed();
    expect(Permission::query()->where('name', 'audit.export')->exists())->toBeFalse();

    $this->artisan('moashir:permissions:sync')->assertSuccessful();

    expect(Permission::query()->where('name', 'audit.export')->exists())->toBeTrue()
        ->and(Role::query()->where('name', 'system_admin')->firstOrFail()->permissionKeys())->toContain('audit.export');
});
