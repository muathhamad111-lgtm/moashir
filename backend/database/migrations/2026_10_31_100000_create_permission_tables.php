<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80);
            $table->string('guard_name', 20)->default('web');
            $table->jsonb('label');
            $table->jsonb('description')->nullable();
            $table->string('module', 20)->index();
            $table->boolean('is_system')->default(true);
            $table->boolean('is_confidential')->default(false);
            $table->boolean('is_grantable')->default(false);
            $table->jsonb('locked_to_roles')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampsTz();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 60);
            $table->string('guard_name', 20)->default('web');
            $table->jsonb('label');
            $table->jsonb('description')->nullable();
            $table->unsignedSmallInteger('dashboard_priority')->default(100);
            $table->boolean('is_system')->default(true);
            $table->boolean('is_assignable')->default(true);
            $table->boolean('is_cycle_scoped')->default(false);
            $table->timestampsTz();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->foreignId('assigned_by_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestampTz('assigned_at')->useCurrent();

            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignId('role_id')->index()->constrained('roles')->cascadeOnDelete();

            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
        });

        DB::statement("ALTER TABLE permissions ADD CONSTRAINT permissions_label_object CHECK (jsonb_typeof(label) = 'object')");
        DB::statement("ALTER TABLE roles ADD CONSTRAINT roles_label_object CHECK (jsonb_typeof(label) = 'object')");
        DB::statement("ALTER TABLE roles ADD CONSTRAINT roles_name_not_super_admin CHECK (name <> 'super_admin')");
    }

    public function down(): void
    {
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
