<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array CHECKS = [
        'reviewed' => '(reviewed_by_user_id IS NULL) = (reviewed_at IS NULL)',
        'rejection_reason' => "rejection_reason IS NULL OR rejection_reason IN ('not_received', 'amount_mismatch', 'unreadable', 'wrong_account', 'duplicate', 'other')",
        'rejection_reviewed' => 'rejection_reason IS NULL OR reviewed_at IS NOT NULL',
        'review_note' => 'review_note IS NULL OR char_length(review_note) BETWEEN 10 AND 500',
        'other_needs_note' => "rejection_reason IS DISTINCT FROM 'other' OR review_note IS NOT NULL",
        'note_with_reason' => 'review_note IS NULL OR rejection_reason IS NOT NULL',
    ];

    public function up(): void
    {
        Schema::create('bank_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('bank_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('proof_file_id')->unique()->constrained('stored_files')->restrictOnDelete();
            $table->foreignId('submitted_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('submitted_at');
            $table->foreignId('reviewed_by_user_id')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->string('rejection_reason', 30)->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestampsTz();

            $table->index(['bank_account_id', 'reviewed_at']);
            $table->index(['submitted_at']);
        });

        foreach (self::CHECKS as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE bank_transfers ADD CONSTRAINT bank_transfers_%s_check CHECK (%s)', $name, $expression));
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION moashir_guard_bank_transfers() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'UPDATE'
                    AND OLD.reviewed_at IS NULL
                    AND NEW.payment_id = OLD.payment_id
                    AND NEW.bank_account_id = OLD.bank_account_id
                    AND NEW.proof_file_id = OLD.proof_file_id
                    AND NEW.submitted_by_user_id = OLD.submitted_by_user_id
                    AND NEW.submitted_at = OLD.submitted_at THEN
                    RETURN NEW;
                END IF;

                RAISE EXCEPTION 'append_only: % on % is not allowed', TG_OP, TG_TABLE_NAME
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER bank_transfers_guard BEFORE UPDATE OR DELETE ON bank_transfers
                FOR EACH ROW EXECUTE FUNCTION moashir_guard_bank_transfers();
            CREATE TRIGGER bank_transfers_guard_truncate BEFORE TRUNCATE ON bank_transfers
                FOR EACH STATEMENT EXECUTE FUNCTION moashir_reject_append_only_change();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS bank_transfers_guard_truncate ON bank_transfers');
        DB::unprepared('DROP TRIGGER IF EXISTS bank_transfers_guard ON bank_transfers');
        Schema::dropIfExists('bank_transfers');
        DB::unprepared('DROP FUNCTION IF EXISTS moashir_guard_bank_transfers()');
    }
};
