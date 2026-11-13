<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const int FIRST_PERIOD_MONTHS = 3;

    private const string VALIDITY_CHECK = "kind <> 'accredited' OR valid_until IS NOT NULL";

    private const string PREVIOUS_OUTCOME = "outcome IN ('valid', 'under_review', 'revoked', 'superseded', 'not_found')";

    private const string CURRENT_OUTCOME = "outcome IN ('valid', 'under_review', 'expired', 'revoked', 'superseded', 'not_found')";

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
                    AND NEW.entity_logo_file_id IS NOT DISTINCT FROM OLD.entity_logo_file_id
                    AND NEW.valid_until IS NOT DISTINCT FROM OLD.valid_until
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
                    AND NEW.entity_logo_file_id IS NOT DISTINCT FROM OLD.entity_logo_file_id
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

    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table): void {
            $table->date('valid_until')->nullable();
            $table->index(['entity_id', 'kind', 'status', 'valid_until']);
        });

        DB::table('certificates')->where('kind', 'accredited')->whereNull('valid_until')->update(['valid_until' => $this->firstPeriodEnd()]);

        DB::statement(sprintf('ALTER TABLE certificates ADD CONSTRAINT certificates_valid_until_check CHECK (%s)', self::VALIDITY_CHECK));
        $this->replaceOutcomeCheck(self::CURRENT_OUTCOME);
        DB::unprepared(self::CURRENT_GUARD);
    }

    public function down(): void
    {
        DB::unprepared(self::PREVIOUS_GUARD);
        DB::table('certificate_verification_logs')->where('outcome', 'expired')->update(['outcome' => 'valid']);
        $this->replaceOutcomeCheck(self::PREVIOUS_OUTCOME);
        DB::statement('ALTER TABLE certificates DROP CONSTRAINT IF EXISTS certificates_valid_until_check');

        Schema::table('certificates', function (Blueprint $table): void {
            $table->dropIndex(['entity_id', 'kind', 'status', 'valid_until']);
            $table->dropColumn('valid_until');
        });
    }

    private function firstPeriodEnd(): string
    {
        $stored = DB::table('settings')->where('key', 'periodic.rollout_from')->value('value');
        $rollout = is_string($stored) ? json_decode($stored) : null;
        $start = is_string($rollout) ? CarbonImmutable::parse($rollout) : CarbonImmutable::now()->firstOfQuarter()->addMonths(self::FIRST_PERIOD_MONTHS);

        return $start->addMonths(self::FIRST_PERIOD_MONTHS)->subDay()->toDateString();
    }

    private function replaceOutcomeCheck(string $expression): void
    {
        DB::statement('ALTER TABLE certificate_verification_logs DROP CONSTRAINT IF EXISTS certificate_verification_logs_outcome_check');
        DB::statement(sprintf('ALTER TABLE certificate_verification_logs ADD CONSTRAINT certificate_verification_logs_outcome_check CHECK (%s)', $expression));
    }
};
