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
        Schema::create('user_identity_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('identity_change_request_id')->constrained()->restrictOnDelete();
            $table->string('field', 20);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->foreignId('requested_by_user_id')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at');

            $table->index(['user_id', 'created_at']);
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION moashir_guard_user_identity_changes() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' AND moashir_entity_erasure_active() THEN
                    RETURN OLD;
                END IF;

                RAISE EXCEPTION 'append_only: % on % is not allowed', TG_OP, TG_TABLE_NAME
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER user_identity_changes_append_only BEFORE UPDATE OR DELETE ON user_identity_changes
                FOR EACH ROW EXECUTE FUNCTION moashir_guard_user_identity_changes();
            CREATE TRIGGER user_identity_changes_append_only_truncate BEFORE TRUNCATE ON user_identity_changes
                FOR EACH STATEMENT EXECUTE FUNCTION moashir_reject_append_only_change();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS user_identity_changes_append_only_truncate ON user_identity_changes');
        DB::unprepared('DROP TRIGGER IF EXISTS user_identity_changes_append_only ON user_identity_changes');
        Schema::dropIfExists('user_identity_changes');
        DB::unprepared('DROP FUNCTION IF EXISTS moashir_guard_user_identity_changes()');
    }
};
