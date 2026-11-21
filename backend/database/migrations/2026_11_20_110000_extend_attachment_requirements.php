<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Required attachments at three levels (criteria batch S5): a main criterion can own requirements too, and each
 * requirement can carry guidance for the entity, the file types it accepts (within the path's types) and the most files
 * it takes (within the path's maximum).
 *
 * The structure guard learns the main-criterion owner, so the requirements of a published version stay frozen, and
 * the guidance joins the name as text that a text correction may change in place.
 */
return new class extends Migration
{
    private const string GUARD = 'attachment_requirements_structure_guard';

    private const string STATUS_WITH_MAIN_OWNER = <<<'SQL'
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

            IF p_table = 'attachment_requirements' AND (p_row->>'main_criterion_id') IS NOT NULL THEN
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
        SQL;

    private const string STATUS_WITHOUT_MAIN_OWNER = <<<'SQL'
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
        SQL;

    private const string DROP_GUARD = 'DROP TRIGGER IF EXISTS attachment_requirements_structure_guard ON attachment_requirements';

    private const string GUARD_WITH_GUIDANCE = "CREATE TRIGGER attachment_requirements_structure_guard BEFORE INSERT OR UPDATE OR DELETE ON attachment_requirements FOR EACH ROW EXECUTE FUNCTION moashir_guard_criteria_node('name', 'guidance')";

    private const string GUARD_NAME_ONLY = "CREATE TRIGGER attachment_requirements_structure_guard BEFORE INSERT OR UPDATE OR DELETE ON attachment_requirements FOR EACH ROW EXECUTE FUNCTION moashir_guard_criteria_node('name')";

    public function up(): void
    {
        Schema::table('attachment_requirements', function (Blueprint $table): void {
            $table->foreignId('main_criterion_id')->nullable()->index()->constrained('main_criteria')->cascadeOnDelete();
            $table->jsonb('guidance')->nullable();
            $table->jsonb('allowed_types')->nullable();
            $table->unsignedSmallInteger('max_files')->nullable();
        });

        DB::statement('ALTER TABLE attachment_requirements DROP CONSTRAINT attachment_requirements_one_owner_check');
        DB::statement('ALTER TABLE attachment_requirements ADD CONSTRAINT attachment_requirements_one_owner_check CHECK (num_nonnulls(main_criterion_id, sub_criterion_id, guiding_point_id) = 1)');
        DB::statement("ALTER TABLE attachment_requirements ADD CONSTRAINT attachment_requirements_guidance_check CHECK (guidance IS NULL OR jsonb_typeof(guidance) = 'object')");
        DB::statement("ALTER TABLE attachment_requirements ADD CONSTRAINT attachment_requirements_allowed_types_check CHECK (allowed_types IS NULL OR jsonb_typeof(allowed_types) = 'array')");
        DB::statement('ALTER TABLE attachment_requirements ADD CONSTRAINT attachment_requirements_max_files_check CHECK (max_files IS NULL OR max_files BETWEEN 1 AND 50)');
        DB::statement('CREATE UNIQUE INDEX attachment_requirements_main_key_unique ON attachment_requirements (main_criterion_id, key) WHERE main_criterion_id IS NOT NULL');

        DB::unprepared(self::STATUS_WITH_MAIN_OWNER);
        DB::unprepared(self::DROP_GUARD);
        DB::unprepared(self::GUARD_WITH_GUIDANCE);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE attachment_requirements DISABLE TRIGGER '.self::GUARD);
        DB::table('attachment_requirements')->whereNotNull('main_criterion_id')->delete();
        DB::statement('ALTER TABLE attachment_requirements ENABLE TRIGGER '.self::GUARD);

        DB::unprepared(self::DROP_GUARD);
        DB::unprepared(self::GUARD_NAME_ONLY);
        DB::unprepared(self::STATUS_WITHOUT_MAIN_OWNER);

        DB::statement('DROP INDEX IF EXISTS attachment_requirements_main_key_unique');
        DB::statement('ALTER TABLE attachment_requirements DROP CONSTRAINT attachment_requirements_max_files_check');
        DB::statement('ALTER TABLE attachment_requirements DROP CONSTRAINT attachment_requirements_allowed_types_check');
        DB::statement('ALTER TABLE attachment_requirements DROP CONSTRAINT attachment_requirements_guidance_check');
        DB::statement('ALTER TABLE attachment_requirements DROP CONSTRAINT attachment_requirements_one_owner_check');

        Schema::table('attachment_requirements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('main_criterion_id');
            $table->dropColumn(['guidance', 'allowed_types', 'max_files']);
        });

        DB::statement('ALTER TABLE attachment_requirements ADD CONSTRAINT attachment_requirements_one_owner_check CHECK (num_nonnulls(sub_criterion_id, guiding_point_id) = 1)');
    }
};
