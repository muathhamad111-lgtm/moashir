<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string PREVIOUS_STATUS = "status IN ('issued', 'revoked', 'superseded')";

    private const string CURRENT_STATUS = "status IN ('issued', 'suspended', 'revoked', 'superseded')";

    private const string SUSPENDED = "(status <> 'suspended' OR suspended_at IS NOT NULL) AND (suspended_at IS NULL OR (status IN ('suspended', 'revoked', 'superseded') AND suspended_reason IS NOT NULL))";

    private const string PREVIOUS_OUTCOME = "outcome IN ('valid', 'revoked', 'superseded', 'not_found')";

    private const string CURRENT_OUTCOME = "outcome IN ('valid', 'under_review', 'revoked', 'superseded', 'not_found')";

    private const string CURRENT_GUARD = <<<'SQL'
            CREATE OR REPLACE FUNCTION moashir_guard_certificates() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'UPDATE'
                    AND NEW.public_id = OLD.public_id
                    AND NEW.number = OLD.number
                    AND NEW.verification_code = OLD.verification_code
                    AND NEW.kind = OLD.kind
                    AND NEW.level IS NOT DISTINCT FROM OLD.level
                    AND NEW.is_test = OLD.is_test
                    AND NEW.entity_id = OLD.entity_id
                    AND NEW.assessment_id = OLD.assessment_id
                    AND NEW.result_snapshot_id = OLD.result_snapshot_id
                    AND NEW.score_percent = OLD.score_percent
                    AND NEW.snapshot = OLD.snapshot
                    AND NEW.issued_at = OLD.issued_at
                    AND NEW.issued_by_user_id IS NOT DISTINCT FROM OLD.issued_by_user_id
                    AND NEW.replaces_certificate_id IS NOT DISTINCT FROM OLD.replaces_certificate_id
                    AND NEW.reissue_reason IS NOT DISTINCT FROM OLD.reissue_reason
                    AND (OLD.pdf_file_id IS NULL OR NEW.pdf_file_id IS NOT DISTINCT FROM OLD.pdf_file_id)
                    AND ((NEW.suspended_at IS NOT DISTINCT FROM OLD.suspended_at AND NEW.suspended_reason IS NOT DISTINCT FROM OLD.suspended_reason)
                        OR (OLD.status = 'issued' AND NEW.status = 'suspended'))
                    AND ((OLD.status = 'issued' OR (OLD.status = 'suspended' AND NEW.status IN ('revoked', 'superseded'))) OR (
                        NEW.status = OLD.status
                        AND NEW.revoked_at IS NOT DISTINCT FROM OLD.revoked_at
                        AND NEW.revoked_reason IS NOT DISTINCT FROM OLD.revoked_reason
                        AND NEW.revoked_by_user_id IS NOT DISTINCT FROM OLD.revoked_by_user_id
                        AND NEW.superseded_at IS NOT DISTINCT FROM OLD.superseded_at
                    )) THEN
                    RETURN NEW;
                END IF;

                RAISE EXCEPTION 'append_only: % on % is not allowed', TG_OP, TG_TABLE_NAME
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$ LANGUAGE plpgsql;
            SQL;

    private const string PREVIOUS_GUARD = <<<'SQL'
            CREATE OR REPLACE FUNCTION moashir_guard_certificates() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'UPDATE'
                    AND NEW.public_id = OLD.public_id
                    AND NEW.number = OLD.number
                    AND NEW.verification_code = OLD.verification_code
                    AND NEW.kind = OLD.kind
                    AND NEW.level IS NOT DISTINCT FROM OLD.level
                    AND NEW.is_test = OLD.is_test
                    AND NEW.entity_id = OLD.entity_id
                    AND NEW.assessment_id = OLD.assessment_id
                    AND NEW.result_snapshot_id = OLD.result_snapshot_id
                    AND NEW.score_percent = OLD.score_percent
                    AND NEW.snapshot = OLD.snapshot
                    AND NEW.issued_at = OLD.issued_at
                    AND NEW.issued_by_user_id IS NOT DISTINCT FROM OLD.issued_by_user_id
                    AND NEW.replaces_certificate_id IS NOT DISTINCT FROM OLD.replaces_certificate_id
                    AND NEW.reissue_reason IS NOT DISTINCT FROM OLD.reissue_reason
                    AND (OLD.pdf_file_id IS NULL OR NEW.pdf_file_id IS NOT DISTINCT FROM OLD.pdf_file_id)
                    AND ((OLD.status = 'issued') OR (
                        NEW.status = OLD.status
                        AND NEW.revoked_at IS NOT DISTINCT FROM OLD.revoked_at
                        AND NEW.revoked_reason IS NOT DISTINCT FROM OLD.revoked_reason
                        AND NEW.revoked_by_user_id IS NOT DISTINCT FROM OLD.revoked_by_user_id
                        AND NEW.superseded_at IS NOT DISTINCT FROM OLD.superseded_at
                    )) THEN
                    RETURN NEW;
                END IF;

                RAISE EXCEPTION 'append_only: % on % is not allowed', TG_OP, TG_TABLE_NAME
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$ LANGUAGE plpgsql;
            SQL;

    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table): void {
            $table->timestampTz('suspended_at')->nullable();
            $table->text('suspended_reason')->nullable();
        });

        $this->replaceCheck('certificates', 'status', self::CURRENT_STATUS);
        DB::statement(sprintf('ALTER TABLE certificates ADD CONSTRAINT certificates_suspended_check CHECK (%s)', self::SUSPENDED));
        $this->replaceCheck('certificate_verification_logs', 'outcome', self::CURRENT_OUTCOME);
        DB::unprepared(self::CURRENT_GUARD);
    }

    public function down(): void
    {
        DB::unprepared(self::PREVIOUS_GUARD);
        $this->replaceCheck('certificate_verification_logs', 'outcome', self::PREVIOUS_OUTCOME);
        DB::statement('ALTER TABLE certificates DROP CONSTRAINT IF EXISTS certificates_suspended_check');
        $this->replaceCheck('certificates', 'status', self::PREVIOUS_STATUS);

        Schema::table('certificates', function (Blueprint $table): void {
            $table->dropColumn(['suspended_at', 'suspended_reason']);
        });
    }

    private function replaceCheck(string $table, string $name, string $expression): void
    {
        DB::statement(sprintf('ALTER TABLE %1$s DROP CONSTRAINT IF EXISTS %1$s_%2$s_check', $table, $name));
        DB::statement(sprintf('ALTER TABLE %1$s ADD CONSTRAINT %1$s_%2$s_check CHECK (%3$s)', $table, $name, $expression));
    }
};
