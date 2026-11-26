<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array CHECKS = [
        'kind' => "kind IN ('accredited')",
        'level' => "level IS NULL OR level IN ('bronze', 'silver', 'gold', 'diamond')",
        'status' => "status IN ('issued', 'revoked', 'superseded')",
        'number' => "number ~ '^(TEST-)?CRT-[0-9]{4}-[0-9]{6}$' AND is_test = (number LIKE 'TEST-%')",
        'verification_code' => "verification_code ~ '^[0-9A-HJKMNP-TV-Z]{20,40}$'",
        'score_percent' => 'score_percent BETWEEN 0 AND 100',
        'snapshot' => "jsonb_typeof(snapshot) = 'object'",
        'revoked' => "(status = 'revoked') = (revoked_at IS NOT NULL AND revoked_reason IS NOT NULL)",
        'superseded' => "(status = 'superseded') = (superseded_at IS NOT NULL)",
        'reissue' => '(replaces_certificate_id IS NULL) = (reissue_reason IS NULL)',
    ];

    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->string('number', 30)->unique();
            $table->string('verification_code', 40)->unique();
            $table->string('kind', 25);
            $table->string('level', 10)->nullable();
            $table->string('status', 12);
            $table->boolean('is_test');
            $table->foreignId('entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('assessment_id')->constrained()->restrictOnDelete();
            $table->foreignId('result_snapshot_id')->constrained('assessment_result_snapshots')->restrictOnDelete();
            $table->decimal('score_percent', 9, 4);
            $table->jsonb('snapshot');
            $table->timestampTz('issued_at');
            $table->foreignId('issued_by_user_id')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->foreignId('pdf_file_id')->nullable()->index()->constrained('stored_files')->restrictOnDelete();
            $table->foreignId('replaces_certificate_id')->nullable()->unique()->constrained('certificates')->restrictOnDelete();
            $table->text('reissue_reason')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->text('revoked_reason')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->timestampTz('superseded_at')->nullable();
            $table->timestampsTz();

            $table->index(['entity_id', 'issued_at']);
            $table->index(['assessment_id', 'kind']);
            $table->index(['status', 'issued_at']);
        });

        foreach (self::CHECKS as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE certificates ADD CONSTRAINT certificates_%s_check CHECK (%s)', $name, $expression));
        }

        DB::statement("CREATE UNIQUE INDEX certificates_one_issued ON certificates (assessment_id, kind) WHERE status = 'issued'");

        DB::unprepared(<<<'SQL'
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
                    AND (OLD.status = 'issued' OR (
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

            CREATE TRIGGER certificates_guard BEFORE UPDATE OR DELETE ON certificates
                FOR EACH ROW EXECUTE FUNCTION moashir_guard_certificates();
            CREATE TRIGGER certificates_guard_truncate BEFORE TRUNCATE ON certificates
                FOR EACH STATEMENT EXECUTE FUNCTION moashir_reject_append_only_change();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS certificates_guard_truncate ON certificates');
        DB::unprepared('DROP TRIGGER IF EXISTS certificates_guard ON certificates');
        Schema::dropIfExists('certificates');
        DB::unprepared('DROP FUNCTION IF EXISTS moashir_guard_certificates()');
    }
};
