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
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('email', 254);
            $table->timestampTz('email_verified_at')->nullable();
            $table->text('phone')->nullable();
            $table->timestampTz('phone_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->boolean('password_change_required')->default(false);
            $table->boolean('is_super_admin')->default(false);
            $table->string('status', 20)->default('active');
            $table->char('locale', 2)->default('ar');
            $table->string('theme', 10)->default('system');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestampTz('two_factor_confirmed_at')->nullable();
            $table->boolean('mfa_required')->default(false);
            $table->unsignedSmallInteger('failed_login_attempts')->default(0);
            $table->timestampTz('last_login_at')->nullable();
            $table->ipAddress('last_login_ip')->nullable();
            $table->timestampTz('password_changed_at')->nullable();
            $table->timestampTz('locked_until')->nullable();
            $table->timestampTz('deactivated_at')->nullable();
            $table->text('deactivation_reason')->nullable();
            $table->rememberToken();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index('status');
            $table->index('deleted_at');
        });

        DB::statement('CREATE UNIQUE INDEX users_email_lower_unique ON users (lower(email))');
        DB::statement('CREATE UNIQUE INDEX users_entity_id_unique ON users (entity_id) WHERE entity_id IS NOT NULL');
        DB::statement('CREATE INDEX users_is_super_admin_index ON users (is_super_admin) WHERE is_super_admin');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('invited', 'active', 'suspended', 'expired'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_locale_check CHECK (locale IN ('ar', 'en'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_theme_check CHECK (theme IN ('light', 'dark', 'system'))");

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email', 254)->primary();
            $table->string('token');
            $table->timestampTz('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
