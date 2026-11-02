<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_criteria_moves', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_version_id')->index()->constrained('criteria_versions')->restrictOnDelete();
            $table->foreignId('to_version_id')->index()->constrained('criteria_versions')->restrictOnDelete();
            $table->string('scope', 20);
            $table->string('triggered_by', 20);
            $table->jsonb('kept');
            $table->jsonb('reset');
            $table->jsonb('added');
            $table->timestampTz('created_at');

            $table->index(['assessment_id', 'id']);
        });

        DB::statement("ALTER TABLE assessment_criteria_moves ADD CONSTRAINT assessment_criteria_moves_scope_check CHECK (scope IN ('all', 'reset_open'))");
        DB::statement("ALTER TABLE assessment_criteria_moves ADD CONSTRAINT assessment_criteria_moves_trigger_check CHECK (triggered_by IN ('criteria_edit', 'entity_restore'))");
        DB::statement('ALTER TABLE assessment_criteria_moves ADD CONSTRAINT assessment_criteria_moves_versions_check CHECK (from_version_id <> to_version_id)');
        DB::statement("ALTER TABLE assessment_criteria_moves ADD CONSTRAINT assessment_criteria_moves_items_check CHECK (jsonb_typeof(kept) = 'array' AND jsonb_typeof(reset) = 'array' AND jsonb_typeof(added) = 'array')");

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER assessment_criteria_moves_append_only BEFORE UPDATE OR DELETE ON assessment_criteria_moves
                FOR EACH ROW EXECUTE FUNCTION moashir_guard_erasable_history();
            CREATE TRIGGER assessment_criteria_moves_append_only_truncate BEFORE TRUNCATE ON assessment_criteria_moves
                FOR EACH STATEMENT EXECUTE FUNCTION moashir_reject_append_only_change();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS assessment_criteria_moves_append_only_truncate ON assessment_criteria_moves');
        DB::unprepared('DROP TRIGGER IF EXISTS assessment_criteria_moves_append_only ON assessment_criteria_moves');
        Schema::dropIfExists('assessment_criteria_moves');
    }
};
