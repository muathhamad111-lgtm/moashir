<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string GUARD = <<<'SQL'
            CREATE OR REPLACE FUNCTION moashir_guard_terms_versions() RETURNS trigger AS $$
            BEGIN
                IF OLD.published_at IS NULL THEN
                    IF TG_OP = 'DELETE' THEN
                        RETURN OLD;
                    END IF;

                    RETURN NEW;
                END IF;

                IF TG_OP = 'UPDATE'
                    AND NEW.kind = OLD.kind
                    AND NEW.version = OLD.version
                    AND NEW.title IS NOT DISTINCT FROM OLD.title
                    AND NEW.body = OLD.body
                    AND NEW.effective_from IS NOT DISTINCT FROM OLD.effective_from
                    AND NEW.published_at IS NOT DISTINCT FROM OLD.published_at
                    AND NEW.published_by_user_id IS NOT DISTINCT FROM OLD.published_by_user_id THEN
                    RETURN NEW;
                END IF;

                RAISE EXCEPTION 'append_only: % on a published terms version is not allowed', TG_OP
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$ LANGUAGE plpgsql;
            SQL;

    public function up(): void
    {
        Schema::table('terms_versions', function (Blueprint $table): void {
            $table->jsonb('title')->nullable();
            $table->timestampTz('published_at')->nullable();
        });

        DB::statement('UPDATE terms_versions SET published_at = created_at');
        DB::statement('ALTER TABLE terms_versions ALTER COLUMN effective_from DROP NOT NULL');
        DB::statement('ALTER TABLE terms_versions ALTER COLUMN published_by_user_id DROP NOT NULL');
        DB::statement("ALTER TABLE terms_versions ADD CONSTRAINT terms_versions_title_object CHECK (title IS NULL OR jsonb_typeof(title) = 'object')");
        DB::statement('ALTER TABLE terms_versions ADD CONSTRAINT terms_versions_published_check CHECK ((published_at IS NULL) = (published_by_user_id IS NULL) AND (published_at IS NULL) = (effective_from IS NULL))');
        DB::statement('ALTER TABLE terms_versions ADD CONSTRAINT terms_versions_current_published CHECK (NOT is_current OR published_at IS NOT NULL)');
        DB::statement('CREATE UNIQUE INDEX terms_versions_one_draft ON terms_versions (kind) WHERE published_at IS NULL');

        DB::unprepared(self::GUARD);
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER terms_versions_published_guard BEFORE UPDATE OR DELETE ON terms_versions
                FOR EACH ROW EXECUTE FUNCTION moashir_guard_terms_versions();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS terms_versions_published_guard ON terms_versions');
        DB::unprepared('DROP FUNCTION IF EXISTS moashir_guard_terms_versions()');
        DB::statement('DELETE FROM terms_versions WHERE published_at IS NULL');
        DB::statement('DROP INDEX IF EXISTS terms_versions_one_draft');
        DB::statement('ALTER TABLE terms_versions DROP CONSTRAINT IF EXISTS terms_versions_current_published');
        DB::statement('ALTER TABLE terms_versions DROP CONSTRAINT IF EXISTS terms_versions_published_check');
        DB::statement('ALTER TABLE terms_versions DROP CONSTRAINT IF EXISTS terms_versions_title_object');
        DB::statement('ALTER TABLE terms_versions ALTER COLUMN published_by_user_id SET NOT NULL');
        DB::statement('ALTER TABLE terms_versions ALTER COLUMN effective_from SET NOT NULL');

        Schema::table('terms_versions', function (Blueprint $table): void {
            $table->dropColumn(['title', 'published_at']);
        });
    }
};
