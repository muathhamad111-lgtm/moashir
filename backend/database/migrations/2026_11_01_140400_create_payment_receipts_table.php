<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array CHECKS = [
        'status' => "status IN ('issued', 'voided')",
        'number' => "(is_test AND number IS NULL) OR (NOT is_test AND number ~ '^RCP-[0-9]{4}-[0-9]{6}$')",
        'voided' => "(status = 'voided') = (voided_at IS NOT NULL)",
        'reissue_count' => 'reissue_count >= 0',
        'snapshot' => "jsonb_typeof(snapshot) = 'object'",
    ];

    public function up(): void
    {
        Schema::create('payment_receipts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->string('number', 20)->nullable()->unique();
            $table->boolean('is_test');
            $table->string('status', 10);
            $table->timestampTz('issued_at');
            $table->jsonb('snapshot');
            $table->foreignId('pdf_file_id')->nullable()->index()->constrained('stored_files')->restrictOnDelete();
            $table->unsignedInteger('reissue_count')->default(0);
            $table->timestampTz('last_reissued_at')->nullable();
            $table->timestampTz('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('voided_by_refund_id')->nullable()->unique()->constrained('refunds')->restrictOnDelete();
            $table->timestampsTz();

            $table->index(['is_test', 'issued_at']);
        });

        foreach (self::CHECKS as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE payment_receipts ADD CONSTRAINT payment_receipts_%s_check CHECK (%s)', $name, $expression));
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION moashir_guard_payment_receipts() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'UPDATE'
                    AND NEW.public_id = OLD.public_id
                    AND NEW.payment_id = OLD.payment_id
                    AND NEW.number IS NOT DISTINCT FROM OLD.number
                    AND NEW.is_test = OLD.is_test
                    AND NEW.issued_at = OLD.issued_at
                    AND NEW.snapshot = OLD.snapshot
                    AND NEW.reissue_count >= OLD.reissue_count
                    AND (OLD.voided_at IS NULL OR (
                        NEW.voided_at = OLD.voided_at
                        AND NEW.void_reason IS NOT DISTINCT FROM OLD.void_reason
                        AND NEW.voided_by_refund_id IS NOT DISTINCT FROM OLD.voided_by_refund_id
                    )) THEN
                    RETURN NEW;
                END IF;

                RAISE EXCEPTION 'append_only: % on % is not allowed', TG_OP, TG_TABLE_NAME
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER payment_receipts_guard BEFORE UPDATE OR DELETE ON payment_receipts
                FOR EACH ROW EXECUTE FUNCTION moashir_guard_payment_receipts();
            CREATE TRIGGER payment_receipts_guard_truncate BEFORE TRUNCATE ON payment_receipts
                FOR EACH STATEMENT EXECUTE FUNCTION moashir_reject_append_only_change();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS payment_receipts_guard_truncate ON payment_receipts');
        DB::unprepared('DROP TRIGGER IF EXISTS payment_receipts_guard ON payment_receipts');
        Schema::dropIfExists('payment_receipts');
        DB::unprepared('DROP FUNCTION IF EXISTS moashir_guard_payment_receipts()');
    }
};
