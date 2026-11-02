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
        Schema::create('criteria_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('version_number')->unique();
            $table->jsonb('label')->nullable();
            $table->string('status', 10)->default('draft');
            $table->string('scale_kind', 10)->nullable();
            $table->jsonb('validation_errors')->nullable();
            $table->foreignId('cloned_from_version_id')->nullable()->index()->constrained('criteria_versions')->nullOnDelete();
            $table->foreignId('published_by_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement("ALTER TABLE criteria_versions ADD CONSTRAINT criteria_versions_status_check CHECK (status IN ('draft', 'published', 'archived'))");
        DB::statement("ALTER TABLE criteria_versions ADD CONSTRAINT criteria_versions_scale_kind_check CHECK (scale_kind IS NULL OR scale_kind IN ('of_10', 'relative'))");
        DB::statement("ALTER TABLE criteria_versions ADD CONSTRAINT criteria_versions_published_scale_check CHECK (status = 'draft' OR (scale_kind IS NOT NULL AND published_at IS NOT NULL))");
        DB::statement("ALTER TABLE criteria_versions ADD CONSTRAINT criteria_versions_label_check CHECK (label IS NULL OR jsonb_typeof(label) = 'object')");
        DB::statement("ALTER TABLE criteria_versions ADD CONSTRAINT criteria_versions_draft_not_deleted_check CHECK (deleted_at IS NULL OR status = 'draft')");
        DB::statement("CREATE UNIQUE INDEX criteria_versions_one_published ON criteria_versions (status) WHERE status = 'published'");
        DB::statement("CREATE UNIQUE INDEX criteria_versions_one_open_draft ON criteria_versions (status) WHERE status = 'draft' AND deleted_at IS NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('criteria_versions');
    }
};
