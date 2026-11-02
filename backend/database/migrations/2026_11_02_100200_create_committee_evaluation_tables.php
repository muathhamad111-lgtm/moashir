<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array ASSIGNMENT_CHECKS = [
        'status' => "status IN ('not_started', 'in_progress', 'completed', 'voided')",
        'completed' => "status <> 'completed' OR (submitted_at IS NOT NULL AND percent IS NOT NULL)",
        'voided' => "(status = 'voided') = (voided_at IS NOT NULL AND void_reason IS NOT NULL)",
        'void_reason' => "void_reason IS NULL OR void_reason IN ('excluded', 'replaced', 'removed', 'withdrawn')",
        'percent' => 'percent IS NULL OR percent BETWEEN 0 AND 100',
        'substitute_approval' => 'substitute_approved_at IS NULL OR replaces_assignment_id IS NOT NULL',
    ];

    private const array SCORE_CHECKS = [
        'level' => "level IN ('sub', 'point')",
        'node' => "(level = 'sub' AND sub_criterion_id IS NOT NULL AND guiding_point_id IS NULL) OR (level = 'point' AND guiding_point_id IS NOT NULL AND sub_criterion_id IS NULL)",
        'raw_value' => 'raw_value BETWEEN 0 AND 100',
        'percent' => 'percent BETWEEN 0 AND 100',
        'version' => 'version >= 1',
    ];

    public function up(): void
    {
        Schema::create('committee_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('award_cycle_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('cycle_committee_assessor_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->string('status', 15);
            $table->foreignId('replaces_assignment_id')->nullable()->unique()->constrained('committee_assignments')->cascadeOnDelete();
            $table->timestampTz('substitute_approved_at')->nullable();
            $table->foreignId('substitute_approved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('returned_at')->nullable();
            $table->decimal('percent', 9, 4)->nullable();
            $table->decimal('total_score', 20, 8)->nullable();
            $table->timestampTz('voided_at')->nullable();
            $table->string('void_reason', 15)->nullable();
            $table->timestampTz('overdue_notified_at')->nullable();
            $table->timestampsTz();

            $table->unique(['cycle_committee_assessor_id', 'assessment_id']);
            $table->index(['assessment_id', 'status']);
        });

        foreach (self::ASSIGNMENT_CHECKS as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE committee_assignments ADD CONSTRAINT committee_assignments_%s_check CHECK (%s)', $name, $expression));
        }

        Schema::create('committee_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('committee_assignment_id')->constrained()->cascadeOnDelete();
            $table->string('level', 5);
            $table->foreignId('sub_criterion_id')->nullable()->index()->constrained('sub_criteria')->restrictOnDelete();
            $table->foreignId('guiding_point_id')->nullable()->index()->constrained('guiding_points')->restrictOnDelete();
            $table->foreignId('scorer_user_id')->index()->constrained('users')->restrictOnDelete();
            $table->decimal('raw_value', 7, 2);
            $table->decimal('percent', 9, 4);
            $table->unsignedInteger('version');
            $table->text('change_reason')->nullable();
            $table->timestampTz('superseded_at')->nullable();
            $table->timestampTz('created_at');

            $table->index(['committee_assignment_id', 'created_at']);
        });

        foreach (self::SCORE_CHECKS as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE committee_scores ADD CONSTRAINT committee_scores_%s_check CHECK (%s)', $name, $expression));
        }

        DB::statement("CREATE UNIQUE INDEX committee_scores_current_sub ON committee_scores (committee_assignment_id, sub_criterion_id) WHERE superseded_at IS NULL AND level = 'sub'");
        DB::statement("CREATE UNIQUE INDEX committee_scores_current_point ON committee_scores (committee_assignment_id, guiding_point_id) WHERE superseded_at IS NULL AND level = 'point'");

        Schema::create('committee_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('committee_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sub_criterion_id')->nullable()->index()->constrained('sub_criteria')->restrictOnDelete();
            $table->text('body');
            $table->unsignedInteger('version');
            $table->foreignId('author_user_id')->index()->constrained('users')->restrictOnDelete();
            $table->timestampTz('superseded_at')->nullable();
            $table->timestampTz('created_at');

            $table->index(['committee_assignment_id', 'created_at']);
        });

        DB::statement('ALTER TABLE committee_notes ADD CONSTRAINT committee_notes_version_check CHECK (version >= 1)');
        DB::statement('CREATE UNIQUE INDEX committee_notes_current_sub ON committee_notes (committee_assignment_id, sub_criterion_id) WHERE superseded_at IS NULL AND sub_criterion_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX committee_notes_current_general ON committee_notes (committee_assignment_id) WHERE superseded_at IS NULL AND sub_criterion_id IS NULL');

        foreach (['committee_scores', 'committee_notes'] as $versioned) {
            DB::unprepared(<<<SQL
                CREATE TRIGGER {$versioned}_versioned BEFORE UPDATE OR DELETE ON {$versioned}
                    FOR EACH ROW EXECUTE FUNCTION moashir_guard_assessment_scores();
                CREATE TRIGGER {$versioned}_versioned_truncate BEFORE TRUNCATE ON {$versioned}
                    FOR EACH STATEMENT EXECUTE FUNCTION moashir_reject_append_only_change();
                SQL);
        }
    }

    public function down(): void
    {
        foreach (['committee_notes', 'committee_scores'] as $versioned) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$versioned}_versioned_truncate ON {$versioned}");
            DB::unprepared("DROP TRIGGER IF EXISTS {$versioned}_versioned ON {$versioned}");
            Schema::dropIfExists($versioned);
        }

        Schema::dropIfExists('committee_assignments');
    }
};
