<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The identity history also records values saved without a request: entered by an administrator, by the Super
 * Admin for themself, at entity registration, or edited by an invitee at acceptance. Only an approved request
 * carries a request id.
 */
return new class extends Migration
{
    private const string SOURCES = "source IN ('approved_request', 'admin_entry', 'super_admin_entry', 'entity_registration', 'invitation_acceptance')";

    public function up(): void
    {
        Schema::table('user_identity_changes', function (Blueprint $table): void {
            $table->string('source', 30)->default('approved_request');
            $table->foreignId('identity_change_request_id')->nullable()->change();
        });

        DB::statement(sprintf('ALTER TABLE user_identity_changes ADD CONSTRAINT user_identity_changes_source_check CHECK (%s)', self::SOURCES));
        DB::statement("ALTER TABLE user_identity_changes ADD CONSTRAINT user_identity_changes_request_check CHECK ((source = 'approved_request') = (identity_change_request_id IS NOT NULL))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE user_identity_changes DROP CONSTRAINT IF EXISTS user_identity_changes_request_check');
        DB::statement('ALTER TABLE user_identity_changes DROP CONSTRAINT IF EXISTS user_identity_changes_source_check');

        // Rows saved without a request cannot satisfy the old NOT NULL request id; the append-only trigger keeps
        // them, so going back is only possible while none exist.
        Schema::table('user_identity_changes', function (Blueprint $table): void {
            $table->foreignId('identity_change_request_id')->nullable(false)->change();
            $table->dropColumn('source');
        });
    }
};
