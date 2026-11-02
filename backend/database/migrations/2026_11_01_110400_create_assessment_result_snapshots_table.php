<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array CHECKS = [
        'kind' => "kind IN ('self_result')",
        'scale_kind' => "scale_kind IN ('of_10', 'relative')",
        'version' => 'version >= 1',
        'percent' => 'percent BETWEEN 0 AND 100',
        'total_score' => 'total_score >= 0 AND total_score <= total_weight',
        'breakdown' => "jsonb_typeof(breakdown) = 'object'",
    ];

    public function up(): void
    {
        Schema::create('assessment_result_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('kind', 25);
            $table->foreignId('criteria_version_id')->index()->constrained()->restrictOnDelete();
            $table->string('scale_kind', 10);
            $table->decimal('total_score', 20, 8);
            $table->decimal('total_weight', 12, 2);
            $table->decimal('percent', 9, 4);
            $table->jsonb('breakdown');
            $table->foreignId('computed_by_user_id')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestampTz('created_at');

            $table->unique(['assessment_id', 'version']);
            $table->index(['assessment_id', 'kind', 'created_at']);
        });

        foreach (self::CHECKS as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE assessment_result_snapshots ADD CONSTRAINT assessment_result_snapshots_%s_check CHECK (%s)', $name, $expression));
        }

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER assessment_result_snapshots_append_only BEFORE UPDATE OR DELETE ON assessment_result_snapshots
                FOR EACH ROW EXECUTE FUNCTION moashir_guard_erasable_history();
            CREATE TRIGGER assessment_result_snapshots_append_only_truncate BEFORE TRUNCATE ON assessment_result_snapshots
                FOR EACH STATEMENT EXECUTE FUNCTION moashir_reject_append_only_change();
            SQL);

        Schema::table('assessments', function (Blueprint $table): void {
            $table->foreign('current_result_snapshot_id')->references('id')->on('assessment_result_snapshots');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->dropForeign(['current_result_snapshot_id']);
        });

        DB::unprepared('DROP TRIGGER IF EXISTS assessment_result_snapshots_append_only_truncate ON assessment_result_snapshots');
        DB::unprepared('DROP TRIGGER IF EXISTS assessment_result_snapshots_append_only ON assessment_result_snapshots');
        Schema::dropIfExists('assessment_result_snapshots');
    }
};
