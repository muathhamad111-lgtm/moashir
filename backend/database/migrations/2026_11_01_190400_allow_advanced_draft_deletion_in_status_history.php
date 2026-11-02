<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION moashir_draft_deletion_target() RETURNS bigint AS $$
                SELECT nullif(coalesce(current_setting('moashir.draft_deletion', true), ''), '')::bigint;
            $$ LANGUAGE sql STABLE;

            CREATE OR REPLACE FUNCTION moashir_guard_status_history() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' AND (moashir_entity_erasure_active() OR OLD.assessment_id = moashir_draft_deletion_target()) THEN
                    RETURN OLD;
                END IF;

                RAISE EXCEPTION 'append_only: % on % is not allowed', TG_OP, TG_TABLE_NAME
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS assessment_status_transitions_append_only ON assessment_status_transitions;
            CREATE TRIGGER assessment_status_transitions_append_only BEFORE UPDATE OR DELETE ON assessment_status_transitions
                FOR EACH ROW EXECUTE FUNCTION moashir_guard_status_history();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS assessment_status_transitions_append_only ON assessment_status_transitions;
            CREATE TRIGGER assessment_status_transitions_append_only BEFORE UPDATE OR DELETE ON assessment_status_transitions
                FOR EACH ROW EXECUTE FUNCTION moashir_guard_erasable_history();
            DROP FUNCTION IF EXISTS moashir_guard_status_history();
            DROP FUNCTION IF EXISTS moashir_draft_deletion_target();
            SQL);
    }
};
