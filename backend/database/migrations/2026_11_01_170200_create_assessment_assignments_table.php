<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array CHECKS = [
        'status' => "status IN ('active', 'reassigned', 'completed', 'ended')",
        'ended' => "(status = 'active') = (ended_at IS NULL)",
        'reassign_reason' => "status <> 'reassigned' OR end_reason IS NOT NULL",
    ];

    public function up(): void
    {
        Schema::create('assessment_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessor_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 15);
            $table->text('reason')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('ended_at')->nullable();
            $table->text('end_reason')->nullable();
            $table->timestampsTz();

            $table->index(['assessment_id', 'created_at']);
            $table->index(['assessor_user_id', 'status']);
        });

        foreach (self::CHECKS as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE assessment_assignments ADD CONSTRAINT assessment_assignments_%s_check CHECK (%s)', $name, $expression));
        }

        DB::statement("CREATE UNIQUE INDEX assessment_assignments_one_active ON assessment_assignments (assessment_id) WHERE status = 'active'");

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION moashir_guard_assessment_assignments() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' AND moashir_entity_erasure_active() THEN
                    RETURN OLD;
                END IF;

                IF TG_OP = 'UPDATE'
                    AND OLD.status = 'active'
                    AND (to_jsonb(NEW) - ARRAY['status', 'started_at', 'ended_at', 'end_reason', 'updated_at'])
                        = (to_jsonb(OLD) - ARRAY['status', 'started_at', 'ended_at', 'end_reason', 'updated_at']) THEN
                    RETURN NEW;
                END IF;

                RAISE EXCEPTION 'append_only: % on % is not allowed', TG_OP, TG_TABLE_NAME
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER assessment_assignments_history BEFORE UPDATE OR DELETE ON assessment_assignments
                FOR EACH ROW EXECUTE FUNCTION moashir_guard_assessment_assignments();
            CREATE TRIGGER assessment_assignments_history_truncate BEFORE TRUNCATE ON assessment_assignments
                FOR EACH STATEMENT EXECUTE FUNCTION moashir_reject_append_only_change();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS assessment_assignments_history_truncate ON assessment_assignments');
        DB::unprepared('DROP TRIGGER IF EXISTS assessment_assignments_history ON assessment_assignments');
        Schema::dropIfExists('assessment_assignments');
        DB::unprepared('DROP FUNCTION IF EXISTS moashir_guard_assessment_assignments()');
    }
};
