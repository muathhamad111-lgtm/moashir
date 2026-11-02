<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array CHECKS = [
        'status' => "status IN ('streamed', 'queued', 'ready', 'failed')",
        'ready' => "status <> 'ready' OR (stored_file_id IS NOT NULL AND completed_at IS NOT NULL)",
        'row_count' => 'row_count >= 0',
        'filters' => "jsonb_typeof(filters) = 'object'",
    ];

    public function up(): void
    {
        Schema::create('finance_exports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('requested_by_user_id')->index()->constrained('users')->restrictOnDelete();
            $table->jsonb('filters');
            $table->unsignedInteger('row_count');
            $table->string('locale', 2);
            $table->string('status', 10);
            $table->foreignId('stored_file_id')->nullable()->unique()->constrained('stored_files')->restrictOnDelete();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
        });

        foreach (self::CHECKS as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE finance_exports ADD CONSTRAINT finance_exports_%s_check CHECK (%s)', $name, $expression));
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION moashir_guard_finance_exports() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'UPDATE'
                    AND OLD.status = 'queued'
                    AND (to_jsonb(NEW) - 'status' - 'stored_file_id' - 'completed_at' - 'updated_at')
                        = (to_jsonb(OLD) - 'status' - 'stored_file_id' - 'completed_at' - 'updated_at') THEN
                    RETURN NEW;
                END IF;

                RAISE EXCEPTION 'append_only: % on % is not allowed', TG_OP, TG_TABLE_NAME
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER finance_exports_guard BEFORE UPDATE OR DELETE ON finance_exports
                FOR EACH ROW EXECUTE FUNCTION moashir_guard_finance_exports();
            CREATE TRIGGER finance_exports_guard_truncate BEFORE TRUNCATE ON finance_exports
                FOR EACH STATEMENT EXECUTE FUNCTION moashir_reject_append_only_change();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS finance_exports_guard_truncate ON finance_exports');
        DB::unprepared('DROP TRIGGER IF EXISTS finance_exports_guard ON finance_exports');
        Schema::dropIfExists('finance_exports');
        DB::unprepared('DROP FUNCTION IF EXISTS moashir_guard_finance_exports()');
    }
};
