<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array APPEND_ONLY = ['committee_averages', 'secretary_decisions'];

    public function up(): void
    {
        Schema::create('committee_averages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('award_cycle_id')->index()->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->decimal('percent', 9, 4);
            $table->unsignedSmallInteger('member_count');
            $table->jsonb('inputs');
            $table->char('inputs_digest', 64);
            $table->boolean('is_manual')->default(false);
            $table->foreignId('computed_by_user_id')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at');

            $table->unique(['assessment_id', 'version']);
            $table->unique(['assessment_id', 'inputs_digest']);
        });

        DB::statement('ALTER TABLE committee_averages ADD CONSTRAINT committee_averages_values_check CHECK (percent BETWEEN 0 AND 100 AND member_count >= 1 AND version >= 1)');

        Schema::table('assessments', function (Blueprint $table): void {
            $table->foreignId('committee_average_id')->nullable()->index()->constrained('committee_averages')->nullOnDelete();
        });

        Schema::create('secretary_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('award_cycle_id')->constrained()->restrictOnDelete();
            $table->foreignId('assessment_id')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->foreignId('region_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->string('kind', 25);
            $table->foreignId('committee_average_id')->nullable()->constrained('committee_averages')->cascadeOnDelete();
            $table->foreignId('committee_assignment_id')->nullable()->constrained('committee_assignments')->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->foreignId('decided_by_user_id')->index()->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at');

            $table->index(['award_cycle_id', 'kind', 'created_at']);
        });

        DB::statement("ALTER TABLE secretary_decisions ADD CONSTRAINT secretary_decisions_kind_check CHECK (kind IN ('approve_average', 'return_to_committee', 'approve_substitute', 'approve_region', 'unapprove_region', 'approve_national', 'announce'))");
        DB::statement("ALTER TABLE secretary_decisions ADD CONSTRAINT secretary_decisions_note_check CHECK (kind NOT IN ('return_to_committee', 'unapprove_region') OR note IS NOT NULL)");
        DB::statement("ALTER TABLE secretary_decisions ADD CONSTRAINT secretary_decisions_target_check CHECK ((kind IN ('approve_average', 'return_to_committee', 'approve_substitute')) = (assessment_id IS NOT NULL) AND (kind IN ('approve_region', 'unapprove_region')) = (region_id IS NOT NULL))");

        foreach (self::APPEND_ONLY as $history) {
            DB::unprepared(<<<SQL
                CREATE TRIGGER {$history}_append_only BEFORE UPDATE OR DELETE ON {$history}
                    FOR EACH ROW EXECUTE FUNCTION moashir_guard_erasable_history();
                CREATE TRIGGER {$history}_append_only_truncate BEFORE TRUNCATE ON {$history}
                    FOR EACH STATEMENT EXECUTE FUNCTION moashir_reject_append_only_change();
                SQL);
        }
    }

    public function down(): void
    {
        foreach (self::APPEND_ONLY as $history) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$history}_append_only_truncate ON {$history}");
            DB::unprepared("DROP TRIGGER IF EXISTS {$history}_append_only ON {$history}");
        }

        Schema::dropIfExists('secretary_decisions');

        Schema::table('assessments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('committee_average_id');
        });

        Schema::dropIfExists('committee_averages');
    }
};
