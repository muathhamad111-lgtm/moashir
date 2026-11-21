<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * The System Administrator now manages the criteria tree from the platform (operator decision D-4, 2026-11-19).
 * `moashir:permissions:sync` only adds brand-new keys to an existing staff role, so the existing role gets the
 * already-catalogued `criteria.manage` here. On a fresh database the role and the key do not exist yet and the
 * sync creates the role with its new defaults.
 */
return new class extends Migration
{
    private const string ROLE = 'system_admin';

    private const string PERMISSION = 'criteria.manage';

    private const string GUARD = 'web';

    public function up(): void
    {
        $roleId = $this->idIn('roles', self::ROLE);
        $permissionId = $this->idIn('permissions', self::PERMISSION);

        if ($roleId === null || $permissionId === null) {
            return;
        }

        DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $roleId = $this->idIn('roles', self::ROLE);
        $permissionId = $this->idIn('permissions', self::PERMISSION);

        if ($roleId === null || $permissionId === null) {
            return;
        }

        DB::table('role_has_permissions')->where(['role_id' => $roleId, 'permission_id' => $permissionId])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function idIn(string $table, string $name): ?int
    {
        $id = DB::table($table)->where(['name' => $name, 'guard_name' => self::GUARD])->value('id');

        return is_numeric($id) ? (int) $id : null;
    }
};
