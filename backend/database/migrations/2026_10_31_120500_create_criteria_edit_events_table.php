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
        Schema::create('criteria_edit_events', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 20);
            $table->string('scope', 15);
            $table->foreignId('from_version_id')->index()->constrained('criteria_versions')->restrictOnDelete();
            $table->foreignId('to_version_id')->nullable()->index()->constrained('criteria_versions')->restrictOnDelete();
            $table->jsonb('changes');
            $table->unsignedInteger('affected_open_count')->default(0);
            $table->text('reason')->nullable();
            $table->foreignId('edited_by_user_id')->index()->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at');

            $table->index('created_at');
        });

        DB::statement("ALTER TABLE criteria_edit_events ADD CONSTRAINT criteria_edit_events_kind_check CHECK (kind IN ('text_correction', 'structural'))");
        DB::statement("ALTER TABLE criteria_edit_events ADD CONSTRAINT criteria_edit_events_scope_check CHECK (scope IN ('all', 'new_only', 'reset_open'))");
        DB::statement("ALTER TABLE criteria_edit_events ADD CONSTRAINT criteria_edit_events_text_scope_check CHECK (kind = 'structural' OR (scope = 'all' AND to_version_id IS NULL))");
        DB::statement("ALTER TABLE criteria_edit_events ADD CONSTRAINT criteria_edit_events_structural_target_check CHECK (kind = 'text_correction' OR to_version_id IS NOT NULL)");
        DB::statement("ALTER TABLE criteria_edit_events ADD CONSTRAINT criteria_edit_events_changes_check CHECK (jsonb_typeof(changes) = 'array')");
    }

    public function down(): void
    {
        Schema::dropIfExists('criteria_edit_events');
    }
};
