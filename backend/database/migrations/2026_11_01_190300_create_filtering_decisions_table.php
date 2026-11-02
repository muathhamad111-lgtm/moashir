<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array CHECKS = [
        'outcome' => "outcome IN ('qualified', 'not_qualified')",
        'override' => "is_override = (outcome = 'qualified' AND initial_percent < threshold_percent)",
        'reason' => "(outcome = 'qualified' AND NOT is_override) OR reason IS NOT NULL",
        'percents' => 'initial_percent BETWEEN 0 AND 100 AND threshold_percent BETWEEN 0 AND 100',
    ];

    public function up(): void
    {
        Schema::create('filtering_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('award_cycle_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('result_snapshot_id')->constrained('assessment_result_snapshots')->cascadeOnDelete();
            $table->string('outcome', 15);
            $table->decimal('initial_percent', 9, 4);
            $table->decimal('threshold_percent', 9, 4);
            $table->boolean('is_override')->default(false);
            $table->text('reason')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('decided_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at');

            $table->index(['assessment_id', 'created_at']);
        });

        foreach (self::CHECKS as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE filtering_decisions ADD CONSTRAINT filtering_decisions_%s_check CHECK (%s)', $name, $expression));
        }

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER filtering_decisions_append_only BEFORE UPDATE OR DELETE ON filtering_decisions
                FOR EACH ROW EXECUTE FUNCTION moashir_guard_erasable_history();
            CREATE TRIGGER filtering_decisions_append_only_truncate BEFORE TRUNCATE ON filtering_decisions
                FOR EACH STATEMENT EXECUTE FUNCTION moashir_reject_append_only_change();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS filtering_decisions_append_only_truncate ON filtering_decisions');
        DB::unprepared('DROP TRIGGER IF EXISTS filtering_decisions_append_only ON filtering_decisions');
        Schema::dropIfExists('filtering_decisions');
    }
};
