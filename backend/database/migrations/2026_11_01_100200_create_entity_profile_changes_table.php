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
        Schema::create('entity_profile_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('changed_by_user_id')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->string('field', 60);
            $table->jsonb('old_value')->nullable();
            $table->jsonb('new_value')->nullable();
            $table->timestampTz('created_at');

            $table->index(['entity_id', 'created_at']);
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION moashir_entity_erasure_active() RETURNS boolean AS $$
                SELECT coalesce(current_setting('moashir.entity_erasure', true), '') = 'on';
            $$ LANGUAGE sql STABLE;

            CREATE OR REPLACE FUNCTION moashir_guard_entity_profile_changes() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' AND moashir_entity_erasure_active() THEN
                    RETURN OLD;
                END IF;

                RAISE EXCEPTION 'append_only: % on % is not allowed', TG_OP, TG_TABLE_NAME
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER entity_profile_changes_append_only BEFORE UPDATE OR DELETE ON entity_profile_changes
                FOR EACH ROW EXECUTE FUNCTION moashir_guard_entity_profile_changes();
            CREATE TRIGGER entity_profile_changes_append_only_truncate BEFORE TRUNCATE ON entity_profile_changes
                FOR EACH STATEMENT EXECUTE FUNCTION moashir_reject_append_only_change();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS entity_profile_changes_append_only_truncate ON entity_profile_changes');
        DB::unprepared('DROP TRIGGER IF EXISTS entity_profile_changes_append_only ON entity_profile_changes');
        Schema::dropIfExists('entity_profile_changes');
        DB::unprepared('DROP FUNCTION IF EXISTS moashir_guard_entity_profile_changes()');
        DB::unprepared('DROP FUNCTION IF EXISTS moashir_entity_erasure_active()');
    }
};
