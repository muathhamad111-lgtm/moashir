<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const array NODE_TEXT_COLUMNS = [
        'main_criteria' => ['name', 'description'],
        'sub_criteria' => ['name', 'description', 'assessor_note_label'],
        'guiding_points' => ['description'],
        'attachment_requirements' => ['name'],
    ];

    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION moashir_criteria_text_correction_active() RETURNS boolean AS $$
                SELECT coalesce(current_setting('moashir.criteria_text_correction', true), '') = 'on';
            $$ LANGUAGE sql STABLE;

            CREATE OR REPLACE FUNCTION moashir_criteria_node_version_status(p_table text, p_row jsonb) RETURNS text AS $$
            DECLARE
                v_sub_id bigint;
                v_status text;
            BEGIN
                IF p_table = 'main_criteria' THEN
                    SELECT v.status INTO v_status FROM criteria_versions v
                        WHERE v.id = (p_row->>'criteria_version_id')::bigint;
                    RETURN v_status;
                END IF;

                IF p_table = 'sub_criteria' THEN
                    SELECT v.status INTO v_status FROM main_criteria m
                        JOIN criteria_versions v ON v.id = m.criteria_version_id
                        WHERE m.id = (p_row->>'main_criterion_id')::bigint;
                    RETURN v_status;
                END IF;

                IF p_table = 'guiding_points' THEN
                    v_sub_id := (p_row->>'sub_criterion_id')::bigint;
                ELSE
                    v_sub_id := coalesce(
                        (p_row->>'sub_criterion_id')::bigint,
                        (SELECT g.sub_criterion_id FROM guiding_points g WHERE g.id = (p_row->>'guiding_point_id')::bigint)
                    );
                END IF;

                SELECT v.status INTO v_status FROM sub_criteria s
                    JOIN main_criteria m ON m.id = s.main_criterion_id
                    JOIN criteria_versions v ON v.id = m.criteria_version_id
                    WHERE s.id = v_sub_id;
                RETURN v_status;
            END;
            $$ LANGUAGE plpgsql STABLE;

            CREATE OR REPLACE FUNCTION moashir_guard_criteria_node() RETURNS trigger AS $$
            DECLARE
                v_status text;
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    v_status := moashir_criteria_node_version_status(TG_TABLE_NAME, to_jsonb(OLD));
                ELSE
                    v_status := moashir_criteria_node_version_status(TG_TABLE_NAME, to_jsonb(NEW));
                END IF;

                IF v_status IS NULL OR v_status = 'draft' THEN
                    IF TG_OP = 'DELETE' THEN
                        RETURN OLD;
                    END IF;
                    RETURN NEW;
                END IF;

                IF TG_OP = 'UPDATE'
                    AND moashir_criteria_text_correction_active()
                    AND (to_jsonb(NEW) - TG_ARGV - 'updated_at') = (to_jsonb(OLD) - TG_ARGV - 'updated_at') THEN
                    RETURN NEW;
                END IF;

                RAISE EXCEPTION 'criteria_structure: % on % of a % criteria version is not allowed', TG_OP, TG_TABLE_NAME, v_status
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$ LANGUAGE plpgsql;

            CREATE OR REPLACE FUNCTION moashir_guard_criteria_version() RETURNS trigger AS $$
            BEGIN
                IF OLD.status = 'draft' THEN
                    IF TG_OP = 'DELETE' THEN
                        RETURN OLD;
                    END IF;
                    RETURN NEW;
                END IF;

                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'criteria_structure: a % criteria version cannot be deleted', OLD.status
                        USING ERRCODE = 'insufficient_privilege';
                END IF;

                IF (to_jsonb(NEW) - ARRAY['status', 'archived_at', 'label', 'updated_at'])
                    IS DISTINCT FROM (to_jsonb(OLD) - ARRAY['status', 'archived_at', 'label', 'updated_at']) THEN
                    RAISE EXCEPTION 'criteria_structure: the frozen attributes of a % criteria version cannot change', OLD.status
                        USING ERRCODE = 'insufficient_privilege';
                END IF;

                IF NEW.status IS DISTINCT FROM OLD.status AND NOT (OLD.status = 'published' AND NEW.status = 'archived') THEN
                    RAISE EXCEPTION 'criteria_structure: a % criteria version cannot become %', OLD.status, NEW.status
                        USING ERRCODE = 'insufficient_privilege';
                END IF;

                IF NEW.label IS DISTINCT FROM OLD.label AND NOT moashir_criteria_text_correction_active() THEN
                    RAISE EXCEPTION 'criteria_structure: the label of a % criteria version changes only through a text correction', OLD.status
                        USING ERRCODE = 'insufficient_privilege';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            SQL);

        DB::unprepared('CREATE TRIGGER criteria_versions_structure_guard BEFORE UPDATE OR DELETE ON criteria_versions FOR EACH ROW EXECUTE FUNCTION moashir_guard_criteria_version()');

        foreach (self::NODE_TEXT_COLUMNS as $table => $textColumns) {
            DB::unprepared(sprintf(
                'CREATE TRIGGER %1$s_structure_guard BEFORE INSERT OR UPDATE OR DELETE ON %1$s FOR EACH ROW EXECUTE FUNCTION moashir_guard_criteria_node(%2$s)',
                $table,
                implode(', ', array_map(static fn (string $column): string => "'{$column}'", $textColumns)),
            ));
        }

        DB::unprepared('CREATE TRIGGER criteria_edit_events_append_only BEFORE UPDATE OR DELETE ON criteria_edit_events FOR EACH ROW EXECUTE FUNCTION moashir_reject_append_only_change()');
        DB::unprepared('CREATE TRIGGER criteria_edit_events_append_only_truncate BEFORE TRUNCATE ON criteria_edit_events FOR EACH STATEMENT EXECUTE FUNCTION moashir_reject_append_only_change()');
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS criteria_edit_events_append_only_truncate ON criteria_edit_events');
        DB::unprepared('DROP TRIGGER IF EXISTS criteria_edit_events_append_only ON criteria_edit_events');

        foreach (array_keys(self::NODE_TEXT_COLUMNS) as $table) {
            DB::unprepared(sprintf('DROP TRIGGER IF EXISTS %1$s_structure_guard ON %1$s', $table));
        }

        DB::unprepared('DROP TRIGGER IF EXISTS criteria_versions_structure_guard ON criteria_versions');
        DB::unprepared('DROP FUNCTION IF EXISTS moashir_guard_criteria_version()');
        DB::unprepared('DROP FUNCTION IF EXISTS moashir_guard_criteria_node()');
        DB::unprepared('DROP FUNCTION IF EXISTS moashir_criteria_node_version_status(text, jsonb)');
        DB::unprepared('DROP FUNCTION IF EXISTS moashir_criteria_text_correction_active()');
    }
};
