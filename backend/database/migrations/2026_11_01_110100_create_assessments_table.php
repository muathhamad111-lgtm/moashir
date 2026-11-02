<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array CHECKS = [
        'path' => "path IN ('self')",
        'status' => "(path = 'self' AND status IN ('draft', 'submitted', 'result_available'))",
        'scale_kind' => "scale_kind IN ('of_10', 'relative')",
        'draft_not_submitted' => "status <> 'draft' OR submitted_at IS NULL",
        'submitted_at' => "status = 'draft' OR submitted_at IS NOT NULL",
        'result' => "status <> 'result_available' OR (completed_at IS NOT NULL AND current_result_snapshot_id IS NOT NULL)",
    ];

    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->string('request_number', 20)->unique();
            $table->foreignId('entity_id')->constrained()->restrictOnDelete();
            $table->string('path', 10);
            $table->string('status', 30);
            $table->foreignId('criteria_version_id')->index()->constrained()->restrictOnDelete();
            $table->string('scale_kind', 10);
            $table->unsignedBigInteger('current_result_snapshot_id')->nullable()->index();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('due_at')->nullable();
            $table->timestampsTz();

            $table->index(['entity_id', 'path', 'created_at']);
            $table->index(['path', 'status']);
        });

        foreach (self::CHECKS as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE assessments ADD CONSTRAINT assessments_%s_check CHECK (%s)', $name, $expression));
        }

        DB::statement("CREATE UNIQUE INDEX assessments_one_open_self_draft ON assessments (entity_id) WHERE path = 'self' AND status = 'draft'");
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
