<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array CHECKS = [
        'level' => "level IN ('sub', 'point')",
        'node' => "(level = 'sub' AND sub_criterion_id IS NOT NULL AND guiding_point_id IS NULL) OR (level = 'point' AND guiding_point_id IS NOT NULL AND sub_criterion_id IS NULL)",
        'scorer_role' => "scorer_role IN ('entity')",
        'raw_value' => 'raw_value BETWEEN 0 AND 100',
        'percent' => 'percent BETWEEN 0 AND 100',
        'version' => 'version >= 1',
    ];

    public function up(): void
    {
        Schema::create('assessment_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->string('level', 5);
            $table->foreignId('sub_criterion_id')->nullable()->index()->constrained('sub_criteria')->restrictOnDelete();
            $table->foreignId('guiding_point_id')->nullable()->index()->constrained('guiding_points')->restrictOnDelete();
            $table->foreignId('scorer_user_id')->index()->constrained('users')->restrictOnDelete();
            $table->string('scorer_role', 10);
            $table->decimal('raw_value', 7, 2);
            $table->decimal('percent', 9, 4);
            $table->unsignedInteger('version');
            $table->text('change_reason')->nullable();
            $table->timestampTz('superseded_at')->nullable();
            $table->timestampTz('created_at');

            $table->index(['assessment_id', 'created_at']);
        });

        foreach (self::CHECKS as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE assessment_scores ADD CONSTRAINT assessment_scores_%s_check CHECK (%s)', $name, $expression));
        }

        DB::statement("CREATE UNIQUE INDEX assessment_scores_current_sub ON assessment_scores (assessment_id, sub_criterion_id, scorer_role) WHERE superseded_at IS NULL AND level = 'sub'");
        DB::statement("CREATE UNIQUE INDEX assessment_scores_current_point ON assessment_scores (assessment_id, guiding_point_id, scorer_role) WHERE superseded_at IS NULL AND level = 'point'");

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION moashir_guard_assessment_scores() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' AND moashir_entity_erasure_active() THEN
                    RETURN OLD;
                END IF;

                IF TG_OP = 'UPDATE'
                    AND OLD.superseded_at IS NULL
                    AND NEW.superseded_at IS NOT NULL
                    AND (to_jsonb(NEW) - 'superseded_at') = (to_jsonb(OLD) - 'superseded_at') THEN
                    RETURN NEW;
                END IF;

                RAISE EXCEPTION 'append_only: % on % is not allowed', TG_OP, TG_TABLE_NAME
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER assessment_scores_versioned BEFORE UPDATE OR DELETE ON assessment_scores
                FOR EACH ROW EXECUTE FUNCTION moashir_guard_assessment_scores();
            CREATE TRIGGER assessment_scores_versioned_truncate BEFORE TRUNCATE ON assessment_scores
                FOR EACH STATEMENT EXECUTE FUNCTION moashir_reject_append_only_change();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS assessment_scores_versioned_truncate ON assessment_scores');
        DB::unprepared('DROP TRIGGER IF EXISTS assessment_scores_versioned ON assessment_scores');
        Schema::dropIfExists('assessment_scores');
        DB::unprepared('DROP FUNCTION IF EXISTS moashir_guard_assessment_scores()');
    }
};
