<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The staff non-disclosure agreement (operator decision, 2026-11-22) is a third kind of terms version. Its published
 * versions carry a change summary shown to staff, which the immutability guard now covers too.
 *
 * down() refuses while any `nda` version exists: dropping the kind would orphan published agreements and their
 * append-only acceptances, which are the legal record. Removing them is a deliberate data decision, not a rollback.
 */
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
                    AND NEW.change_summary IS NOT DISTINCT FROM OLD.change_summary
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

    private const string PREVIOUS_GUARD = <<<'SQL'
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
            $table->jsonb('change_summary')->nullable();
        });

        DB::statement("ALTER TABLE terms_versions ADD CONSTRAINT terms_versions_change_summary_object CHECK (change_summary IS NULL OR jsonb_typeof(change_summary) = 'object')");
        DB::statement('ALTER TABLE terms_versions DROP CONSTRAINT terms_versions_kind_check');
        DB::statement("ALTER TABLE terms_versions ADD CONSTRAINT terms_versions_kind_check CHECK (kind IN ('terms', 'privacy', 'nda'))");
        DB::unprepared(self::GUARD);
    }

    public function down(): void
    {
        if (DB::table('terms_versions')->where('kind', 'nda')->exists()) {
            throw new RuntimeException('Non-disclosure agreement versions exist; they and their acceptances must be handled before rolling back.');
        }

        DB::unprepared(self::PREVIOUS_GUARD);
        DB::statement('ALTER TABLE terms_versions DROP CONSTRAINT terms_versions_kind_check');
        DB::statement("ALTER TABLE terms_versions ADD CONSTRAINT terms_versions_kind_check CHECK (kind IN ('terms', 'privacy'))");
        DB::statement('ALTER TABLE terms_versions DROP CONSTRAINT terms_versions_change_summary_object');

        Schema::table('terms_versions', function (Blueprint $table): void {
            $table->dropColumn('change_summary');
        });
    }
};
